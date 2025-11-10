<?php

declare(strict_types=1);

namespace Muxtorov98\Kafka\Support;

final class WorkerPrinter
{
    private const BLUE = "\033[34m";
    private const GREEN = "\033[32m";
    private const YELLOW = "\033[33m";
    private const RED = "\033[31m";
    private const RESET = "\033[0m";

    public static function info(string $message): void
    {
        echo self::GREEN . "[INFO] " . self::RESET . $message . PHP_EOL;
    }

    public static function topicHeader(string $topic, string $group, int $concurrency): void
    {
        echo PHP_EOL;
        echo self::YELLOW . "Topic: {$topic} " . self::RESET . "(group={$group}, concurrency={$concurrency})" . PHP_EOL;
    }

    public static function workerStart(string $topic, string $group, int $index, int $pid): void
    {
        $num = str_pad((string)$index, 2, '0', STR_PAD_LEFT);
        echo "  " . self::BLUE . "[WORKER-{$num}]" . self::RESET . " PID={$pid} started" . PHP_EOL;
    }

    public static function allReady(): void
    {
        echo PHP_EOL . self::GREEN . "[OK] All workers are ready & listening..." . self::RESET . PHP_EOL . PHP_EOL;
    }

    public static function error(string $message): void
    {
        echo self::RED . "[ERROR] " . self::RESET . $message . PHP_EOL;
    }
}