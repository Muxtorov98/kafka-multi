<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Muxtorov98\Kafka\Attributes\KafkaChannel;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use Muxtorov98\Kafka\KafkaOptions;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SymfonyAutoDiscovery
{
    /**
     * @param string $projectDir Symfony project_root (kernel->getProjectDir())
     * @return array<string, array{
     *     class: class-string,
     *     group: ?string,
     *     concurrency: int,
     *     maxAttempts: int|null,
     *     backoffMs: int|null,
     *     middlewares: string[],
     *     dlq: string|null,
     *     priority: int,
     *     batchSize: int
     * }>
     */
    public static function discover(KafkaOptions $options, string $projectDir): array
    {
        $paths = $options->discovery['paths'] ?? [];
        $namespaces = $options->discovery['namespaces'] ?? [];

        // Resolve %kernel.project_dir%
        $resolvedPaths = [];
        foreach ($paths as $path) {
            $path = str_replace('%kernel.project_dir%', $projectDir, $path);
            if (is_dir($path)) {
                $resolvedPaths[] = $path;
            }
        }

        // Load all PHP files from handler dirs
        foreach ($resolvedPaths as $path) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    require_once $file->getRealPath();
                }
            }
        }

        $map = [];

        foreach (get_declared_classes() as $class) {
            // Match namespace rules
            if ($namespaces) {
                $matched = false;
                foreach ($namespaces as $ns) {
                    $ns = rtrim($ns, '\\') . '\\';
                    if (str_starts_with($class, $ns)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) continue;
            }

            if (!is_subclass_of($class, KafkaHandlerInterface::class)) continue;

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