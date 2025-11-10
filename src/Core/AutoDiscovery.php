<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka;

use Muxtorov98\Kafka\Attributes\KafkaChannel;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AutoDiscovery
{
    /**
     * @return array<string, array<int, array{
     *   class: class-string,
     *   group: ?string,
     *   concurrency: int,
     *   maxAttempts: int|null,
     *   backoffMs: int|null,
     *   middlewares: string[],
     *   dlq: string|null,
     *   priority: int,
     *   batchSize: int
     * }>>
     *
     * Format:
     * [
     *   'order-created' => [
     *       ['class' => H1::class, 'group' => 'order-service', ...],
     *       ['class' => H2::class, 'group' => 'analytics-group', ...],
     *   ],
     *   'payment-failed' => [
     *       ['class' => H3::class, 'group' => 'billing-service', ...],
     *   ],
     * ]
     */
    public static function discover(KafkaOptions $options): array
    {
        $paths = $options->discovery['paths'] ?? [];
        $namespaces = $options->discovery['namespaces'] ?? [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $realPath = $file->getRealPath();
                if ($realPath === false || substr($realPath, -4) !== '.php') {
                    continue;
                }

                require_once $realPath;
            }
        }

        $map = [];

        foreach (get_declared_classes() as $class) {
            // Namespace filter
            if ($namespaces) {
                $matched = false;
                foreach ($namespaces as $ns) {
                    $ns = rtrim($ns, '\\') . '\\';
                    if (str_starts_with($class, $ns)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }

            // Must implement interface
            if (!is_subclass_of($class, KafkaHandlerInterface::class)) {
                continue;
            }

            $rc = new \ReflectionClass($class);
            $attrs = $rc->getAttributes(KafkaChannel::class);
            if (!$attrs) {
                continue;
            }

            /** @var KafkaChannel $ch */
            $ch = $attrs[0]->newInstance();

            $meta = [
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

            // ✅ Append: topic uchun bir nechta handler bo‘lishi mumkin
            $map[$ch->topic] ??= [];
            $map[$ch->topic][] = $meta;
        }

        return $map;
    }
}