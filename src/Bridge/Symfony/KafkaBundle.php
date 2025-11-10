<?php

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Muxtorov98\Kafka\Bridge\Symfony\DependencyInjection\KafkaExtension;

class KafkaBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new KafkaExtension();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}