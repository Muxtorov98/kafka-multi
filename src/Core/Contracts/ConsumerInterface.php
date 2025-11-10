<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Contracts;

interface ConsumerInterface
{
    public function run(): int; // exit code
}