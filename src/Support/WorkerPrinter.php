<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Support;

final class WorkerPrinter
{
    private const RESET = "\033[0m";
    private const GREEN = "\033[32m";
    private const BLUE = "\033[34m";
    private const YELLOW = "\033[33m";
    private const RED = "\033[31m";
    private const CYAN = "\033[36m";

    public static function info(string $text): void
    {
        echo self::GREEN . "[INFO] " . self::RESET . $text . PHP_EOL;
    }

    public static function warning(string $text): void
    {
        echo self::YELLOW . "[WARN] " . self::RESET . $text . PHP_EOL;
    }

    public static function error(string $text): void
    {
        echo self::RED . "[ERROR] " . self::RESET . $text . PHP_EOL;
    }

    public static function topicHeader(string $topic, string $group, int $concurrency): void
    {
        echo PHP_EOL . self::CYAN . "Topic: {$topic} (group={$group}, concurrency={$concurrency})" . self::RESET . PHP_EOL;
    }

    public static function workerStart(string $topic, string $group, int $number, int $pid): void
    {
        echo "  " . self::BLUE . "[WORKER-".str_pad((string)$number, 2, '0', STR_PAD_LEFT)."]" . self::RESET .
            " PID={$pid} started" . PHP_EOL;
    }

    public static function topicReady(string $topic, int $count): void
    {
        echo self::GREEN . "✅ Topic '{$topic}' has {$count} worker(s) running" . self::RESET . PHP_EOL;
    }

    public static function allReady(): void
    {
        echo PHP_EOL . self::GREEN . "[OK] All workers are ready & listening..." . self::RESET . PHP_EOL . PHP_EOL;
    }
}