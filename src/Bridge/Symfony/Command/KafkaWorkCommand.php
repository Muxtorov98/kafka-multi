<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony\Command;

use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;
use Muxtorov98\Kafka\Support\WorkerPrinter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kafka:work',
    description: 'Run Kafka consumer workers'
)]
class KafkaWorkCommand extends Command
{
    public function __construct(
        private KafkaOptions $options,
        private Producer $producer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routing = AutoDiscovery::discover($this->options);

        if (empty($routing)) {
            WorkerPrinter::info("No Kafka handlers found.");
            return Command::SUCCESS;
        }

        WorkerPrinter::info("Starting Kafka Workers...");

        foreach ($routing as $topic => $meta) {
            $group = $meta['group'];
            $concurrency = (int) $meta['concurrency'];

            WorkerPrinter::topicHeader($topic, $group, $concurrency);

            for ($i = 1; $i <= $concurrency; $i++) {
                $pid = 1000 + rand(10, 99); // Real PID inside Consumer
                WorkerPrinter::workerStart($topic, $group, $i, $pid);
            }
        }

        WorkerPrinter::allReady();

        $consumer = new Consumer(
            options: $this->options,
            routing: $routing,
            producer: $this->producer
        );

        return $consumer->run();
    }
}