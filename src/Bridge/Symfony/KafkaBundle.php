<?php

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class KafkaBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new DependencyInjection\KafkaExtension();
    }
}