<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

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