<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Middleware;

use Muxtorov98\Kafka\Contracts\MiddlewareInterface;
use Muxtorov98\Kafka\DTO\Message;
use Muxtorov98\Kafka\DTO\Context;

final class Pipeline
{
    /**
     * @param list<class-string<MiddlewareInterface>> $middlewares
     */
    public function __construct(
        private array $middlewares
    ) {}

    /**
     * @param callable(Message, Context): void $destination
     */
    public function handle(Message $message, Context $context, callable $destination): void
    {
        $stack = array_reverse($this->middlewares);
        $next = $destination;

        foreach ($stack as $mwClass) {
            $prev = $next;
            $next = function (Message $m, Context $c) use ($mwClass, $prev) {
                /** @var MiddlewareInterface $mw */
                $mw = new $mwClass();
                $mw->process($m, $c, $prev);
            };
        }

        $next($message, $context);
    }
}