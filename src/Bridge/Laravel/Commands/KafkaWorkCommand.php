<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Laravel\Commands;

use Illuminate\Console\Command;
use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;
use Muxtorov98\Kafka\Support\WorkerPrinter;

class KafkaWorkCommand extends Command
{
    protected $signature = 'kafka:work {--demo : Preview only, do not start workers}';
    protected $description = 'Run Kafka consumer workers';

    public function handle(KafkaOptions $options, Producer $producer): int
    {
        $routing = AutoDiscovery::discover($options);

        if (empty($routing)) {
            WorkerPrinter::warning("No Kafka handlers found.");
            return self::SUCCESS;
        }

        WorkerPrinter::info("Starting Kafka Workers...\n");

        foreach ($routing as $topic => $meta) {
            $group       = $meta['group'] ?? 'default-group';
            $concurrency = (int) $meta['concurrency'];

            WorkerPrinter::topicHeader($topic, $group, $concurrency);

            // Preview mode only
            for ($i = 1; $i <= $concurrency; $i++) {
                $pid = random_int(1000, 9999);
                WorkerPrinter::workerStart($topic, $group, $i, $pid);
            }

            WorkerPrinter::topicReady($topic, $concurrency);
        }

        WorkerPrinter::allReady();

        if ($this->option('demo')) {
            return self::SUCCESS;
        }

        // Start real Kafka workers
        $consumer = new Consumer(
            options: $options,
            routing: $routing,
            producer: $producer
        );

        return $consumer->run();
    }
}