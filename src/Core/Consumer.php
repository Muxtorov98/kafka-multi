<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use Muxtorov98\Kafka\Contracts\ConsumerInterface;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use Muxtorov98\Kafka\DTO\Message;
use Muxtorov98\Kafka\DTO\Context;
use Muxtorov98\Kafka\Middleware\Pipeline;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;

/**
 * PRO Consumer:
 * - Concurrency (fork per topic)
 * - Batch (effective batch size)
 * - Retry (per-handler override)
 * - Backoff (per-handler override)
 * - DLQ (per-handler or {topic}{dlq_suffix})
 * - Middlewares (global + local merge)
 * - Graceful shutdown
 */
final class Consumer implements ConsumerInterface
{
    private bool $running = true;

    public function __construct(
        private KafkaOptions $options,
        /** @var array<string, array{
         *   class: class-string,
         *   group: ?string,
         *   concurrency: int,
         *   maxAttempts: int|null,
         *   backoffMs: int|null,
         *   middlewares: string[],
         *   dlq: string|null,
         *   priority: int,
         *   batchSize: int
         * }> */
        private array $routing,
        private ?Producer $producer = null // for DLQ publish
    ) {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT, fn() => $this->running = false);
        }
    }

    public function run(): int
    {
        // Priority bo‘yicha ishga tushirishni hohlasangiz, bu yerda sort qilishingiz mumkin.
        foreach ($this->routing as $topic => $meta) {
            $group = $meta['group'] ?? ('group-' . md5($topic));
            $this->forkGroupConsumer(
                topic: $topic,
                group: $group,
                handlerClass: $meta['class'],
                concurrency: $meta['concurrency'],
                meta: $meta
            );
        }

        while ($this->running) {
            sleep(1);
        }
        return 0;
    }

    private function forkGroupConsumer(string $topic, string $group, string $handlerClass, int $concurrency, array $meta): void
    {
        $children = max(1, $concurrency);
        for ($i = 0; $i < $children; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Cannot fork');
            }
            if ($pid === 0) {
                $this->consumeLoop($topic, $group, $handlerClass, $meta);
                exit(0);
            }
        }
    }

    private function consumeLoop(string $topic, string $group, string $handlerClass, array $meta): void
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->options->brokers);
        $conf->set('group.id', $group);

        foreach ($this->options->consumer as $k => $v) {
            $conf->set((string)$k, (string)$v);
        }

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe([$topic]);

        /** @var KafkaHandlerInterface $handler */
        $handler = new $handlerClass();

        // Effective settings (merge global + attribute)
        $globalMaxAttempts = (int)($this->options->retry['max_attempts'] ?? 3);
        $globalBackoffMs   = (int)($this->options->retry['backoff_ms']   ?? 500);
        $globalDlqSuffix   = (string)($this->options->retry['dlq_suffix'] ?? '-dlq');

        $effMaxAttempts = (int)($meta['maxAttempts'] ?? $globalMaxAttempts);
        $effBackoffMs   = (int)($meta['backoffMs']   ?? $globalBackoffMs);

        $globalBatch    = (int)($this->options->consumer['batch_size'] ?? 1);
        $effBatchSize   = max(1, (int)($meta['batchSize'] ?? $globalBatch));

        // Merge middlewares: global + per-handler
        $globalMws = is_array($this->options->middlewares ?? null) ? $this->options->middlewares : [];
        $localMws  = $meta['middlewares'] ?? [];
        $effMws    = array_values(array_unique([...$globalMws, ...$localMws]));
        $pipeline  = new Pipeline($effMws);

        // Resolve DLQ topic
        $dlqTopic = $meta['dlq'] ?? ($topic . $globalDlqSuffix);
        if ($dlqTopic === $topic) {
            // Oldini olish: DLQ original topic bilan bir xil bo‘lmasin
            $dlqTopic .= '-dead';
        }

        $invoker = new HandlerInvoker();

        // Main consume loop
        while ($this->running) {
            $msgs = [];
            $deadlineMs = 1000; // poll window

            // Batch collect
            for ($i = 0; $i < $effBatchSize; $i++) {
                $msg = $consumer->consume($deadlineMs);
                if ($msg === null) continue;

                if ($msg->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
                    $payload = json_decode((string)$msg->payload, true) ?? [];
                    $headers = is_array($msg->headers) ? $msg->headers : [];

                    $dto = new Message(
                        topic: $msg->topic_name,
                        partition: $msg->partition,
                        offset: $msg->offset,
                        key: $msg->key,
                        payload: $payload,
                        headers: $headers
                    );
                    $msgs[] = $dto;
                } elseif (in_array($msg->err, [RD_KAFKA_RESP_ERR__PARTITION_EOF, RD_KAFKA_RESP_ERR__TIMED_OUT], true)) {
                    // idle; break early for responsiveness
                    break;
                } else {
                    // other errors - skip or log
                }
            }

            if (!$msgs) {
                continue;
            }

            // Process each message with retry + middleware
            foreach ($msgs as $dto) {
                $attempt = 0;
                while (true) {
                    $attempt++;
                    $ctx = new Context(
                        topic: $topic,
                        group: $group,
                        partition: $dto->partition,
                        offset: $dto->offset,
                        attempt: $attempt,
                        maxAttempts: $effMaxAttempts,
                        backoffMs: $effBackoffMs,
                        dlqTopic: $dlqTopic,
                        batchSize: $effBatchSize,
                        headers: $dto->headers,
                        meta: ['priority' => (int)($meta['priority'] ?? 0)]
                    );

                    try {
                        $pipeline->handle($dto, $ctx, function (Message $m, Context $c) use ($handler, $invoker) {
                            $invoker->invoke($handler, $m);
                        });

                        // success → break retry loop
                        break;
                    } catch (\Throwable $e) {
                        if ($attempt >= $effMaxAttempts) {
                            // Give up → DLQ
                            if ($this->producer) {
                                try {
                                    $this->producer->send($dlqTopic, [
                                        'error'   => get_class($e) . ': ' . $e->getMessage(),
                                        'payload' => $dto->payload,
                                        'meta'    => [
                                            'topic'     => $dto->topic,
                                            'partition' => $dto->partition,
                                            'offset'    => $dto->offset,
                                            'key'       => $dto->key,
                                            'headers'   => $dto->headers,
                                            'attempts'  => $attempt,
                                        ],
                                    ]);
                                } catch (\Throwable) {
                                    // DLQ send failed → swallow or log
                                }
                            }
                            // stop retry
                            break;
                        }

                        // Backoff and retry
                        usleep(max(0, $effBackoffMs) * 1000);
                    }
                }
            }
        }

        $consumer->unsubscribe();
        $consumer->close();
    }
}