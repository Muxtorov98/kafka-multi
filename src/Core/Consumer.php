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
use RdKafka\TopicPartition;

final class Consumer implements ConsumerInterface
{
    private bool $running = true;

    /**
     * @var array<string, array<int, array{
     *   class: class-string,
     *   group: ?string,
     *   concurrency: int,
     *   maxAttempts: int|null,
     *   backoffMs: int|null,
     *   middlewares: string[],
     *   dlq: string|null,
     *   priority: int,
     *   batchSize: int
     * }>>
     */
    public function __construct(
        private KafkaOptions $options,
        private array $routing,
        private ?Producer $producer = null
    ) {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT,  fn() => $this->running = false);
        }
    }

    public function run(): int
    {
        // Har bir topic uchun, undagi har bir handler konfiguratsiyasi bo‘yicha alohida fork
        foreach ($this->routing as $topic => $handlers) {
            foreach ($handlers as $meta) {
                $group = $meta['group'] ?? ('group-' . md5($topic . ':' . ($meta['class'] ?? '')));
                $this->forkGroupConsumer(
                    topic: $topic,
                    group: $group,
                    handlerClass: $meta['class'],
                    concurrency: max(1, (int)$meta['concurrency']),
                    meta: $meta
                );
            }
        }

        while ($this->running) {
            sleep(1);
        }
        return 0;
    }

    private function forkGroupConsumer(string $topic, string $group, string $handlerClass, int $concurrency, array $meta): void
    {
        $pids = [];

        // Chiroyli sarlavha – bu yerda minimal chiqish (Bridge darajada to‘liq banner bor)
        fwrite(STDOUT, "Topic: {$topic} (group={$group}, concurrency={$concurrency})\n");

        for ($i = 0; $i < $concurrency; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('❌ Failed to fork worker');
            }

            if ($pid === 0) {
                // Child
                $wid = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
                $pidNum = getmypid();
                fwrite(STDOUT, "  [WORKER-{$wid}] PID={$pidNum} started\n");
                $this->consumeLoop($topic, $group, $handlerClass, $meta);
                exit(0);
            }

            $pids[] = $pid;
        }

        if (count($pids) > 0) {
            fwrite(STDOUT, "[OK] All workers are ready & listening...\n\n");
        }
    }

    private function consumeLoop(string $topic, string $group, string $handlerClass, array $meta): void
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', (string)$this->options->brokers);
        $conf->set('group.id', $group);

        $consumerKafka = $this->options->consumer['kafka'] ?? [];
        foreach ($consumerKafka as $k => $v) {
            $conf->set((string)$k, is_bool($v) ? ($v ? 'true' : 'false') : (string)$v);
        }

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

        $globalMaxAttempts = (int)($this->options->retry['max_attempts'] ?? 3);
        $globalBackoffMs   = (int)($this->options->retry['backoff_ms']   ?? 500);
        $globalDlqSuffix   = (string)($this->options->retry['dlq_suffix'] ?? '-dlq');

        $effMaxAttempts = (int)($meta['maxAttempts'] ?? $globalMaxAttempts);
        $effBackoffMs   = (int)($meta['backoffMs']   ?? $globalBackoffMs);

        $globalBatch  = (int)($this->options->consumer['batch_size'] ?? 1);
        $effBatchSize = max(1, (int)($meta['batchSize'] ?? $globalBatch));

        $globalMws = is_array($this->options->middlewares ?? null) ? $this->options->middlewares : [];
        $localMws  = $meta['middlewares'] ?? [];
        $effMws    = array_values(array_unique([...$globalMws, ...$localMws]));
        $pipeline  = new Pipeline($effMws);

        $dlqTopic = $meta['dlq'] ?? ($topic . $globalDlqSuffix);
        if ($dlqTopic === $topic) {
            $dlqTopic .= '-dead';
        }

        $autoCommit = $this->isAutoCommitEnabled($consumerKafka);
        $invoker    = new HandlerInvoker();

        while ($this->running) {
            $msgs = [];
            for ($i = 0; $i < $effBatchSize; $i++) {
                $msg = $consumer->consume(1000);
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
                    $msgs[] = [$dto, $msg];
                } elseif (in_array($msg->err, [RD_KAFKA_RESP_ERR__PARTITION_EOF, RD_KAFKA_RESP_ERR__TIMED_OUT], true)) {
                    break;
                } else {
                    // other errors → ignore/log
                }
            }

            if (!$msgs) {
                continue;
            }

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
                        (new Pipeline($effMws))->handle($dto, $ctx, function (Message $m, Context $c) use ($handler, $invoker) {
                            $invoker->invoke($handler, $m);
                        });

                        if (!$autoCommit) {
                            $tp = new TopicPartition($dto->topic, $dto->partition, $dto->offset + 1);
                            $consumer->commitAsync($tp);
                        }

                        break; // success
                    } catch (\Throwable $e) {
                        if ($attempt >= $effMaxAttempts) {
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
                                    // swallow
                                }
                            }
                            break; // give up
                        }

                        usleep(max(0, $effBackoffMs) * 1000);
                    }
                }
            }
        }

        $consumer->unsubscribe();
        $consumer->close();
    }

    private function isAutoCommitEnabled(array $consumerKafka): bool
    {
        $v = $consumerKafka['enable.auto.commit'] ?? 'true';
        if (is_bool($v)) return $v;
        $s = strtolower((string)$v);
        return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
    }
}