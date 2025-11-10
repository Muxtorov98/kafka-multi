<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use Muxtorov98\Kafka\DTO\Message;

final class HandlerInvoker
{
    /**
     * Invokes handler based on signature:
     *
     * - If handler expects Message DTO → provide Message
     * - If handler expects array        → provide payload array
     */
    public function invoke(KafkaHandlerInterface $handler, Message $message): void
    {
        $rm = new \ReflectionMethod($handler, 'handle');
        $params = $rm->getParameters();

        // If has typed param and it is Message → pass Message DTO
        if ($params && $params[0]->hasType()) {
            $type = (string)$params[0]->getType();
            if (str_contains($type, 'Message')) {
                $handler->handle($message);
                return;
            }
        }

        // Default: pass array payload
        $handler->handle($message->payload);
    }
}