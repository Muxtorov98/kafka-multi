<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony\Command;

use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kafka:work',
    description: 'Run Kafka consumer workers'
)]
final class KafkaWorkCommand extends Command
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

        if (!$routing) {
            $output->writeln("<comment>⚠️ No Kafka handlers found.</comment>");
            return Command::SUCCESS;
        }

        $output->writeln("<info>🔍 Kafka handlers discovered:</info>");
        foreach ($routing as $topic => $meta) {
            $group = $meta['group'] ?? 'auto';
            $output->writeln("  • <comment>{$topic}</comment> → {$meta['class']} (group: {$group})");
        }

        $output->writeln("<info>🚀 Starting Kafka workers...</info>");

        $consumer = new Consumer(
            options: $this->options,
            routing: $routing,
            producer: $this->producer
        );

        return $consumer->run();
    }
}