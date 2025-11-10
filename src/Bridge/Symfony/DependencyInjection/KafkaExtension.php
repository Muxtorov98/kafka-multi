<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony\DependencyInjection;

use Muxtorov98\Kafka\KafkaOptions;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class KafkaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $processor = new Processor();
        $configuration = new KafkaConfiguration();
        $config = $processor->processConfiguration($configuration, $configs);

        // Kafka config as container parameter
        $container->setParameter('kafka', $config);

        // KafkaOptions as service
        $container->register(KafkaOptions::class, KafkaOptions::class)
            ->setFactory([KafkaOptions::class, 'fromArray'])
            ->addArgument($config)
            ->setPublic(true);
    }
}