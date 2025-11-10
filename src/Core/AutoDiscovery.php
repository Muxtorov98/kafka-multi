<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use Muxtorov98\Kafka\Attributes\KafkaChannel;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AutoDiscovery
{
    /**
     * @return array<string, array{
     *   class: class-string,
     *   group: ?string,
     *   concurrency: int,
     *   maxAttempts: int|null,
     *   backoffMs: int|null,
     *   middlewares: string[],
     *   dlq: string|null,
     *   priority: int,
     *   batchSize: int
     * }>
     */
    public static function discover(KafkaOptions $options): array
    {
        foreach ($options->discovery['paths'] as $path) {
            if (!is_dir($path)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($it as $file) {
                if (is_file($file) && pathinfo((string)$file, PATHINFO_EXTENSION) === 'php') {
                    @require_once (string)$file;
                }
            }
        }

        $map = [];
        foreach (get_declared_classes() as $class) {
            if (!empty($options->discovery['namespaces'])) {
                $ok = false;
                foreach ($options->discovery['namespaces'] as $ns) {
                    if (str_starts_with($class, rtrim($ns, '\\') . '\\')) { $ok = true; break; }
                }
                if (!$ok) continue;
            }

            if (!is_subclass_of($class, KafkaHandlerInterface::class)) {
                continue;
            }

            $rc = new \ReflectionClass($class);
            $attrs = $rc->getAttributes(KafkaChannel::class);
            if (!$attrs) continue;

            /** @var KafkaChannel $ch */
            $ch = $attrs[0]->newInstance();

            $map[$ch->topic] = [
                'class'       => $class,
                'group'       => $ch->group,
                'concurrency' => max(1, (int)$ch->concurrency),
                'maxAttempts' => $ch->maxAttempts,
                'backoffMs'   => $ch->backoffMs,
                'middlewares' => $ch->middlewares ?? [],
                'dlq'         => $ch->dlq,
                'priority'    => (int)$ch->priority,
                'batchSize'   => max(1, (int)$ch->batchSize),
            ];
        }

        return $map;
    }
}