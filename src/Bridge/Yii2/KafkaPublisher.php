<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Yii2;

use Muxtorov98\Kafka\KafkaPublisher as CorePublisher;

final class KafkaPublisher
{
    public function __construct(
        private CorePublisher $core
    ) {}

    public function send(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->core->send($topic, $payload, $key, $headers);
    }

    public function sendBatch(string $topic, array $messages, ?string $key = null, array $headers = []): void
    {
        $this->core->sendBatch($topic, $messages, $key, $headers);
    }

    public function sendAsync(string $topic, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->core->sendAsync($topic, $payload, $key, $headers);
    }

    public function sendWithCallback(string $topic, array $payload, callable $onSuccess, ?callable $onError = null, ?string $key = null, array $headers = []): void
    {
        $this->core->sendWithCallback($topic, $payload, $onSuccess, $onError, $key, $headers);
    }

    public function sendPartitioned(string $topic, int $partition, array $payload, ?string $key = null, array $headers = []): void
    {
        $this->core->sendPartitioned($topic, $partition, $payload, $key, $headers);
    }
}