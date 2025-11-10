<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Muxtorov98\Kafka\KafkaOptions;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class KafkaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(KafkaOptions::class, KafkaOptions::class)
            ->setFactory([ConfigFactory::class, 'fromPhp'])
            ->addArgument('%kernel.project_dir%/config/kafka.php')
            ->setPublic(true);

        $container->autowire(\Muxtorov98\Kafka\Producer::class)
            ->setPublic(true);

        $container->autowire(KafkaPublisher::class)
            ->setPublic(true);

        $container->autowire(Command\KafkaWorkCommand::class)
            ->addTag('console.command');
    }
}