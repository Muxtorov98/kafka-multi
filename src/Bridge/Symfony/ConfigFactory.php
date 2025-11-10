<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Muxtorov98\Kafka\KafkaOptions;

final class ConfigFactory
{
    public static function fromPhp(string $path): KafkaOptions
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Kafka config file not found: {$path}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException("Kafka config must return array in: {$path}");
        }

        return KafkaOptions::fromArray($config);
    }
}