<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use RdKafka\Conf;
use RdKafka\Producer as RdProducer;
use RdKafka\TopicConf;

final class Producer
{
    private ?RdProducer $producer = null;

    public function __construct(
        private KafkaOptions $options
    ) {}

    /**
     * Sync send with flush
     */
    public function send(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->produce($topic, RD_KAFKA_PARTITION_UA, $payload, $key, $headers);
        $this->flushOrFail();
    }

    /**
     * Sync batch send with single flush
     * @param array<int, array> $messages
     */
    public function sendBatch(string $topic, array $messages, ?string $key = null, array $headers = []): void
    {
        foreach ($messages as $p) {
            $this->produce($topic, RD_KAFKA_PARTITION_UA, $p, $key, $headers);
        }
        $this->flushOrFail();
    }

    /**
     * Fire & forget (no flush)
     */
    public function sendAsync(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->produce($topic, RD_KAFKA_PARTITION_UA, $payload, $key, $headers);
        $this->getProducer()->poll(0);
    }

    /**
     * Sync send with callbacks
     *
     * @param callable $onSuccess fn():void
     * @param callable|null $onError fn(\Throwable $e):void
     */
    public function sendWithCallback(
        string $topic,
        array $payload,
        callable $onSuccess,
        ?callable $onError = null,
        ?string $key = null,
        array $headers = []
    ): void {
        try {
            $this->produce($topic, RD_KAFKA_PARTITION_UA, $payload, $key, $headers);
            $this->flushOrFail();
            $onSuccess();
        } catch (\Throwable $e) {
            if ($onError) {
                $onError($e);
                return;
            }
            throw $e;
        }
    }

    /**
     * Send to specific partition (sync)
     */
    public function sendToPartition(
        string $topic,
        int $partition,
        array $payload,
        ?string $key = null,
        array $headers = []
    ): void {
        $this->produce($topic, $partition, $payload, $key, $headers);
        $this->flushOrFail();
    }

    // ===== Internals =====================================================

    private function getProducer(): RdProducer
    {
        if ($this->producer instanceof RdProducer) {
            return $this->producer;
        }

        $conf = new Conf();

        // Brokers
        $conf->set('metadata.broker.list', (string)$this->options->brokers);

        // Producer native configs
        $producerKafka = $this->options->producer['kafka'] ?? [];
        foreach ($producerKafka as $k => $v) {
            // librdkafka expects string values
            $conf->set((string)$k, is_bool($v) ? ($v ? 'true' : 'false') : (string)$v);
        }

        // Optional: Security (SASL/SSL)
        if (!empty($this->options->security['protocol'])) {
            $conf->set('security.protocol', (string)$this->options->security['protocol']);
        }
        if (!empty($this->options->security['sasl']) && is_array($this->options->security['sasl'])) {
            foreach ($this->options->security['sasl'] as $k => $v) {
                $conf->set('sasl.' . (string)$k, (string)$v);
            }
        }
        if (!empty($this->options->security['ssl']) && is_array($this->options->security['ssl'])) {
            // Typical keys: ca.location, certificate.location, key.location, key.password
            foreach ($this->options->security['ssl'] as $k => $v) {
                $conf->set('ssl.' . (string)$k, (string)$v);
            }
        }

        // Lazy create
        $this->producer = new RdProducer($conf);
        return $this->producer;
    }

    private function produce(
        string $topic,
        int $partition,
        array $payload,
        ?string $key,
        array $headers
    ): void {
        $rd = $this->getProducer();

        // Build topic (topic-level config optional)
        $topicConf = new TopicConf();
        $rdTopic = $rd->newTopic($topic, $topicConf);

        $msg = $this->encode($payload);
        $hdr = $this->normalizeHeaders($headers);

        // producev supports headers directly
        $rdTopic->producev(
            $partition,
            0,          // msgflags (RD_KAFKA_MSG_F_BLOCK | 0)
            $msg,
            $key,
            $hdr ?: null
        );

        // drive delivery reports, etc.
        $rd->poll(0);
    }

    private function encode(array $payload): string
    {
        $msg = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($msg === false) {
            throw new \RuntimeException('Kafka Producer: JSON encode failed: ' . json_last_error_msg());
        }
        return $msg;
    }

    /**
     * librdkafka headers: array<string, string|int|float|null>
     * We'll cast scalars to string; null allowed (header with no value)
     *
     * @param array<string, mixed> $headers
     * @return array<string, ?string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            if ($v === null) {
                $out[(string)$k] = null;
                continue;
            }
            if (is_scalar($v)) {
                $out[(string)$k] = (string)$v;
                continue;
            }
            // Fallback: json-encode complex header values
            $out[(string)$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $out;
    }

    /**
     * Flush with small retry loop
     */
    private function flushOrFail(int $timeoutMs = 1000, int $maxTries = 3): void
    {
        $rd = $this->getProducer();
        for ($i = 0; $i < $maxTries; $i++) {
            $result = $rd->flush($timeoutMs);
            if ($result === RD_KAFKA_RESP_ERR_NO_ERROR) {
                return;
            }
        }
        throw new \RuntimeException('Kafka Producer: flush failed after retries');
    }
}