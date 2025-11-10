<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Contracts;

interface ProducerInterface
{
    public function send(string $topic, array $payload, ?string $key = null, array $headers = []): void;
}