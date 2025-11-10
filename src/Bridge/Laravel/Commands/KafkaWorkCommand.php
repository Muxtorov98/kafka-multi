<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;
use Muxtorov98\Kafka\Support\WorkerPrinter;

class KafkaWorkCommand extends Command
{
    protected $signature = 'kafka:work';
    protected $description = 'Run Kafka consumer workers';

    public function handle(KafkaOptions $options, Producer $producer): int
    {
        $routing = AutoDiscovery::discover($options);

        if (empty($routing)) {
            WorkerPrinter::info("No Kafka handlers found.");
            return self::SUCCESS;
        }

        WorkerPrinter::info("Starting Kafka Workers...");

        foreach ($routing as $topic => $meta) {
            $group = $meta['group'];
            $concurrency = (int) $meta['concurrency'];

            WorkerPrinter::topicHeader($topic, $group, $concurrency);

            for ($i = 1; $i <= $concurrency; $i++) {
                $pid = 2000 + rand(10, 99);
                WorkerPrinter::workerStart($topic, $group, $i, $pid);
            }
        }

        WorkerPrinter::allReady();

        $consumer = new Consumer(
            options: $options,
            routing: $routing,
            producer: $producer
        );

        return $consumer->run();
    }
}