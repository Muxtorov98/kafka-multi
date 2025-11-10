<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\DTO;

final class Context
{
    public function __construct(
        public string $topic,
        public string $group,
        public int $partition,
        public int $offset,
        public int $attempt,          // current attempt (starts from 1)
        public int $maxAttempts,      // effective max attempts
        public int $backoffMs,        // effective backoff
        public ?string $dlqTopic,     // resolved DLQ topic or null
        public int $batchSize,        // effective batch size
        public array $headers = [],
        public array $meta = []       // free-form (priority, etc.)
    ) {}
}