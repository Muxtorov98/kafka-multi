<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

final class KafkaOptions
{
    public function __construct(
        public readonly string $brokers,
        public readonly array $consumer = [],
        public readonly array $producer = [],
        public readonly array $retry = [
            'max_attempts' => 3,
            'backoff_ms' => 500,
        ],
        // Discovery configuration (1: C-variant)
        public readonly array $discovery = [
            // namespaces yoki prefixes: ["App\\Kafka\\Handlers\\", "Common\\Kafka\\Handlers\\"]
            'namespaces' => [],
            // psr-4 papkalar: ["app/Kafka/Handlers", "common/kafka/handlers"]
            'paths' => [],
        ],
        public readonly array $security = [],
    ) {}

    public static function fromArray(array $cfg): self
    {
        return new self(
            brokers: $cfg['brokers'] ?? 'localhost:9092',
            consumer: $cfg['consumer'] ?? [],
            producer: $cfg['producer'] ?? [],
            retry: $cfg['retry'] ?? ['max_attempts' => 3, 'backoff_ms' => 500],
            discovery: $cfg['discovery'] ?? ['namespaces' => [], 'paths' => []],
            security: $cfg['security'] ?? [],
        );
    }
}