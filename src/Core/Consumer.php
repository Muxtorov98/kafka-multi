<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use Muxtorov98\Kafka\Contracts\ConsumerInterface;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use Muxtorov98\Kafka\Contracts\MiddlewareInterface;
use Muxtorov98\Kafka\DTO\Message;
use Muxtorov98\Kafka\DTO\Context;
use Muxtorov98\Kafka\Middleware\Pipeline;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\TopicPartition;

/**
 * PRO Consumer (eventsiz):
 * - Concurrency (fork)
 * - Batch consume
 * - Retry + Backoff + DLQ (Producer bilan)
 * - Global + local middlewares merge
 * - Auto/Manual commit
 * - Graceful shutdown (SIGINT/SIGTERM)
 * - Kafka native config = alohida, custom config = alohida
 */
final class Consumer implements ConsumerInterface
{
    private bool $running = true;

    /**
     * @param array<string, array{
     *   class: class-string,
     *   group: ?string,
     *   concurrency: int,
     *   maxAttempts: int|null,
     *   backoffMs: int|null,
     *   middlewares: string[],
     *   dlq: string|null,
     *   priority: int,
     *   batchSize: int
     * }> $routing
     */
    public function __construct(
        private KafkaOptions $options,
        private array $routing,
        private ?Producer $producer = null // DLQ uchun ixtiyoriy
    ) {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT,  fn() => $this->running = false);
        }
    }

    public function run(): int
    {
        // (ixtiyoriy) priority bo‘yicha sort qilishingiz mumkin
        foreach ($this->routing as $topic => $meta) {
            $group = $meta['group'] ?? ('group-' . md5($topic));
            $this->forkGroupConsumer(
                topic: $topic,
                group: $group,
                handlerClass: $meta['class'],
                concurrency: max(1, (int)$meta['concurrency']),
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
        $pids = [];

        for ($i = 0; $i < $concurrency; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('❌ Failed to fork worker');
            }

            if ($pid === 0) {
                // Child process
                $pid = getmypid();
                fwrite(STDOUT, "  [WORKER-".str_pad((string)($i+1), 2, '0', STR_PAD_LEFT)."] PID={$pid} started\n");
                $this->consumeLoop($topic, $group, $handlerClass, $meta);
                exit(0);
            }

            // Parent: save child PID
            $pids[] = $pid;
        }

        // Parent: only print ONCE for this topic
        $count = count($pids);
        if ($count > 0) {
            fwrite(STDOUT, "✅ Topic '{$topic}' has {$count} worker(s) running\n");
        }
    }

    private function consumeLoop(string $topic, string $group, string $handlerClass, array $meta): void
    {
        // --- Build Kafka Conf (faqat native) ---
        $conf = new Conf();
        $conf->set('metadata.broker.list', (string)$this->options->brokers);
        $conf->set('group.id', $group);

        // consumer.kafka ichida faqat librdkafka paramlar bo‘lishi kerak
        $consumerKafka = $this->options->consumer['kafka'] ?? [];
        foreach ($consumerKafka as $k => $v) {
            $conf->set((string)$k, is_bool($v) ? ($v ? 'true' : 'false') : (string)$v);
        }

        // Security (ixtiyoriy)
        if (!empty($this->options->security['protocol'])) {
            $conf->set('security.protocol', (string)$this->options->security['protocol']);
        }
        if (!empty($this->options->security['sasl']) && is_array($this->options->security['sasl'])) {
            foreach ($this->options->security['sasl'] as $k => $v) {
                $conf->set('sasl.' . (string)$k, (string)$v);
            }
        }
        if (!empty($this->options->security['ssl']) && is_array($this->options->security['ssl'])) {
            foreach ($this->options->security['ssl'] as $k => $v) {
                $conf->set('ssl.' . (string)$k, (string)$v);
            }
        }

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe([$topic]);

        /** @var KafkaHandlerInterface $handler */
        $handler = new $handlerClass();

        // --- Effective settings (global + per-handler) ---
        $globalMaxAttempts = (int)($this->options->retry['max_attempts'] ?? 3);
        $globalBackoffMs   = (int)($this->options->retry['backoff_ms']   ?? 500);
        $globalDlqSuffix   = (string)($this->options->retry['dlq_suffix'] ?? '-dlq');

        $effMaxAttempts = (int)($meta['maxAttempts'] ?? $globalMaxAttempts);
        $effBackoffMs   = (int)($meta['backoffMs']   ?? $globalBackoffMs);

        // batch: handler override > global custom
        $globalBatch  = (int)($this->options->consumer['batch_size'] ?? 1);
        $effBatchSize = max(1, (int)($meta['batchSize'] ?? $globalBatch));

        // merge middlewares: global + local (unique)
        $globalMws = is_array($this->options->middlewares ?? null) ? $this->options->middlewares : [];
        $localMws  = $meta['middlewares'] ?? [];
        $effMws    = array_values(array_unique([...$globalMws, ...$localMws]));
        $pipeline  = new Pipeline($effMws);

        // DLQ topic
        $dlqTopic = $meta['dlq'] ?? ($topic . $globalDlqSuffix);
        if ($dlqTopic === $topic) {
            $dlqTopic .= '-dead';
        }

        // commit usuli
        $autoCommit = $this->isAutoCommitEnabled($consumerKafka);

        $invoker = new HandlerInvoker();

        // --- Main loop ---
        while ($this->running) {
            $msgs = [];
            // collect batch
            for ($i = 0; $i < $effBatchSize; $i++) {
                $msg = $consumer->consume(1000); // 1s poll
                if ($msg === null) {
                    continue;
                }

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
                    // message object + underlying kafka message (for manual commit)
                    $msgs[] = [$dto, $msg];
                } elseif (in_array($msg->err, [RD_KAFKA_RESP_ERR__PARTITION_EOF, RD_KAFKA_RESP_ERR__TIMED_OUT], true)) {
                    // idle / timeout – batchni erta yakunlash
                    break;
                } else {
                    // boshqa xatolar: loglash mumkin
                    // continue
                }
            }

            if (!$msgs) {
                continue;
            }

            // process batch (retry per message)
            foreach ($msgs as [$dto, $raw]) {
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
                        // middleware pipeline → handler
                        $pipeline->handle($dto, $ctx, function (Message $m, Context $c) use ($handler, $invoker) {
                            $invoker->invoke($handler, $m);
                        });

                        // success → manual commit (agar auto emas)
                        if (!$autoCommit) {
                            $tp = new TopicPartition($dto->topic, $dto->partition, $dto->offset + 1);
                            $consumer->commitAsync($tp);
                        }

                        break; // success
                    } catch (\Throwable $e) {
                        if ($attempt >= $effMaxAttempts) {
                            // DLQ
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
                                    // DLQ yuborishda xato – yutib yuboramiz (log qilishingiz mumkin)
                                }
                            }

                            // manual commit? (xohishga qarab)
                            // if (!$autoCommit) { $consumer->commitAsync(); }

                            break; // give up
                        }

                        // backoff va qayta urinish
                        usleep(max(0, $effBackoffMs) * 1000);
                    }
                }
            }
        }

        // shutdown
        $consumer->unsubscribe();
        $consumer->close();
    }

    private function isAutoCommitEnabled(array $consumerKafka): bool
    {
        // librdkafka default: enable.auto.commit = true
        $v = $consumerKafka['enable.auto.commit'] ?? 'true';
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower((string)$v);
        return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
    }
}