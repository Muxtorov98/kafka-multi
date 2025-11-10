<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Contracts;

use Muxtorov98\Kafka\DTO\Message;

interface KafkaHandlerInterface
{
    /**
     * C-variant: Handler ikkala uslubni ham qabul qiladi.
     * - handle(array $message)
     * - handle(Message $message)
     */
    public function handle(Message|array $message): void;
}