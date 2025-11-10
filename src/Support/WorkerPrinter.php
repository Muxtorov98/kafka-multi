<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Support;

/**
 * Unified & Colored CLI output for Kafka Workers
 * Works for Laravel, Symfony, Yii2
 */
final class WorkerPrinter
{
    public static function info(string $message): void
    {
        self::line("\033[32m[INFO]\033[0m {$message}");
    }

    public static function ok(string $message): void
    {
        self::line("\033[32m[OK]\033[0m {$message}");
    }

    public static function error(string $message): void
    {
        self::line("\033[31m[ERROR]\033[0m {$message}");
    }

    public static function topicHeader(string $topic, string $group, int $concurrency): void
    {
        self::line("\n\033[36mTopic:\033[0m {$topic} (group={$group}, concurrency={$concurrency})");
    }

    public static function workerStart(string $topic, string $group, int $index, int $pid): void
    {
        $num = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        self::line("  \033[34m[WORKER-{$num}]\033[0m PID={$pid} started");
    }

    public static function allReady(): void
    {
        self::line("\n\033[32m[OK]\033[0m All workers are ready & listening...\n");
    }

    private static function line(string $text): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }
}