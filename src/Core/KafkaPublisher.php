<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use RdKafka\ProducerTopic;

final class KafkaPublisher
{
    public function __construct(
        private Producer $producer
    ) {}

    /**
     * Sync Send (flush bilan)
     */
    public function send(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->producer->send($topic, $payload, $key, $headers);
    }

    /**
     * Batch Send (sync)
     *
     * @param array<int, array> $messages
     */
    public function sendBatch(string $topic, array $messages, ?string $key = null, array $headers = []): void
    {
        foreach ($messages as $payload) {
            $this->producer->send($topic, $payload, $key, $headers);
        }
    }

    /**
     * 🔥 Async send (fire & forget)
     * Flush kutmaydi — juda tez
     */
    public function sendAsync(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->producer->sendAsync($topic, $payload, $key, $headers);
    }

    /**
     * 🎯 Send with callback (success/error listener)
     */
    public function sendWithCallback(
        string $topic,
        array $payload,
        callable $onSuccess,
        ?callable $onError = null,
        ?string $key = null,
        array $headers = []
    ): void {
        $this->producer->sendWithCallback($topic, $payload, $onSuccess, $onError, $key, $headers);
    }

    /**
     * 📌 Send to specific partition
     */
    public function sendPartitioned(
        string $topic,
        int $partition,
        array $payload,
        ?string $key = null,
        array $headers = []
    ): void {
        $this->producer->sendToPartition($topic, $partition, $payload, $key, $headers);
    }
}