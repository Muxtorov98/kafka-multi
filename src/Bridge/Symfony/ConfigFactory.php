<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Muxtorov98\Kafka\KafkaOptions;

final class ConfigFactory
{
    public static function create(array $config): KafkaOptions
    {
        return KafkaOptions::fromArray($config);
    }
}