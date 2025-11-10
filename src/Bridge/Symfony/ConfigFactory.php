<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Symfony;

use Muxtorov98\Kafka\KafkaOptions;
use Symfony\Component\HttpKernel\KernelInterface;

final class ConfigFactory
{
    /**
     * Symfony kernel bilan `config/kafka.php`ni o‘qiydi.
     * Agar fayl bo‘lmasa — DEFAULT config bilan qaytadi (ishlayveradi).
     */
    public static function fromPhp(KernelInterface $kernel): KafkaOptions
    {
        $projectDir = $kernel->getProjectDir();
        $configFile = $projectDir . '/config/kafka.php';

        if (file_exists($configFile)) {
            $config = require $configFile;
            if (!is_array($config)) {
                throw new \RuntimeException("config/kafka.php array qaytarishi kerak");
            }
            return KafkaOptions::fromArray($config);
        }

        // Fallback: default config (0-config ishlashi uchun)
        return KafkaOptions::fromArray([
            'brokers' => $_ENV['KAFKA_BROKERS'] ?? 'kafka:9092',

            'consumer' => [
                'kafka' => [
                    'enable.auto.commit' => true,
                    'auto.offset.reset'  => 'earliest',
                ],
                'batch_size' => 1,
            ],

            'producer' => [
                'kafka' => [
                    'acks'             => 'all',
                    'compression.type' => 'lz4',
                    'linger.ms'        => 1,
                ],
            ],

            'retry' => [
                'max_attempts' => 3,
                'backoff_ms'   => 500,
                'dlq_suffix'   => '-dlq',
            ],

            'discovery' => [
                'namespaces' => ['App\\Kafka\\Handlers\\'],
                'paths'      => [$projectDir . '/src/Kafka/Handlers'],
            ],
        ]);
    }
}