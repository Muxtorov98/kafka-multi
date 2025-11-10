<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Muxtorov98\Kafka\Bridge\Symfony\DependencyInjection\KafkaExtension;

final class KafkaBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new KafkaExtension();
    }

    public function getPath(): string
    {
        // Bundle path (PSR-4)
        return \dirname(__DIR__);
    }
}