<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Laravel\Commands;

use Illuminate\Console\Command;
use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;

final class KafkaWorkCommand extends Command
{
    protected $signature = 'kafka:work';
    protected $description = 'Start Kafka workers';

    public function handle(KafkaOptions $options): int
    {
        $routing = AutoDiscovery::discover($options);
        $consumer = new Consumer($options, $routing);
        return $consumer->run();
    }
}