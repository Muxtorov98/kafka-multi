<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Muxtorov98\Kafka\KafkaOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ConfigFactory
{
    public static function create(ContainerInterface $container): KafkaOptions
    {
        $config = $container->getParameter('kafka');

        if (!is_array($config)) {
            throw new \RuntimeException("Kafka configuration must be an array. Given: " . gettype($config));
        }

        return KafkaOptions::fromArray($config);
    }
}