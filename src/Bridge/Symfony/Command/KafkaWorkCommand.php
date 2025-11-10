<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony\Command;

use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class KafkaWorkCommand extends Command
{
    protected static $defaultName = 'kafka:work';

    public function __construct(private KafkaOptions $options)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routing = AutoDiscovery::discover($this->options);
        $consumer = new Consumer($this->options, $routing);
        return $consumer->run();
    }
}