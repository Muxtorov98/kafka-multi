<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Contracts;

use Muxtorov98\Kafka\DTO\Message;
use Muxtorov98\Kafka\DTO\Context;

interface MiddlewareInterface
{
    /**
     * PRO middleware: message + context + next
     */
    public function process(Message $message, Context $context, callable $next): void;
}