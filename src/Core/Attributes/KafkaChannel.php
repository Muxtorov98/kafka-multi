<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class KafkaChannel
{
    public function __construct(
        public string $topic,
        public ?string $group = null,

        // PRO: Worker count
        public int $concurrency = 1,

        // PRO: Retry Strategy (override global)
        public ?int $maxAttempts = null,
        public ?int $backoffMs = null,

        // PRO: Middleware pipeline
        public array $middlewares = [],

        // PRO: Dead Letter Queue topic
        public ?string $dlq = null,

        // PRO: Priority (if scheduler exists)
        public int $priority = 0,

        // PRO: Batch consumption
        public int $batchSize = 1,
    ) {}
}