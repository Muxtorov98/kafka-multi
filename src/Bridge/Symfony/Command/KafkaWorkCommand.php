<?php

namespace Muxtorov98\Kafka\Bridge\Symfony\Command;

use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
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
    public function __construct(private KafkaOptions $options)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consumer = new Consumer($this->options);
        return $consumer->run();
    }
}