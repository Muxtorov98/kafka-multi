<?php

namespace Muxtorov98\Kafka\Bridge\Symfony\DependencyInjection;

use Muxtorov98\Kafka\KafkaOptions;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class KafkaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Merge config
        $config = array_replace_recursive(...$configs);

        // Register KafkaOptions as a service
        $container->register(KafkaOptions::class, KafkaOptions::class)
            ->setFactory([self::class, 'createOptions'])
            ->addArgument($config)
            ->setPublic(true);
    }

    public static function createOptions(array $config): KafkaOptions
    {
        return KafkaOptions::fromArray($config);
    }
}