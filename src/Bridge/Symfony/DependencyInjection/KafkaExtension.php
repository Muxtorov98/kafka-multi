<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony\DependencyInjection;

use Muxtorov98\Kafka\Bridge\Symfony\ConfigFactory;
use Muxtorov98\Kafka\Bridge\Symfony\KafkaPublisher;
use Muxtorov98\Kafka\Bridge\Symfony\Command\KafkaWorkCommand;
use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelInterface;

final class KafkaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // 1) KafkaOptions (config/kafka.php dan o‘qiladi, yo‘q bo‘lsa default)
        $defOptions = new Definition(KafkaOptions::class);
        $defOptions->setFactory([ConfigFactory::class, 'fromPhp']);
        $defOptions->setArguments([new Reference('kernel')]); // KernelInterface
        $defOptions->setAutowired(false)->setPublic(true);
        $container->setDefinition(KafkaOptions::class, $defOptions);

        // 2) Producer
        $defProducer = new Definition(Producer::class);
        $defProducer->setAutowired(true)->setPublic(true);
        $container->setDefinition(Producer::class, $defProducer);

        // 3) Symfony-friendly Publisher (bridge)
        $defPub = new Definition(KafkaPublisher::class);
        $defPub->setAutowired(true)->setPublic(true);
        $container->setDefinition(KafkaPublisher::class, $defPub);

        // 4) CLI Command: kafka:work
        $defCmd = new Definition(KafkaWorkCommand::class);
        $defCmd->setAutowired(true)->addTag('console.command');
        $container->setDefinition(KafkaWorkCommand::class, $defCmd);
    }
}