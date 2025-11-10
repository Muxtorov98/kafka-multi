<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\DTO;

final class Message
{
    public function __construct(
        public readonly string $topic,
        public readonly int $partition,
        public readonly int $offset,
        public readonly ?string $key,
        public readonly array $payload,
        public readonly array $headers = []
    ) {}
}