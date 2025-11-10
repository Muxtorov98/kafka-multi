<?php
// services.php/yaml orqali ham bo‘lishi mumkin
use Muxtorov98\Kafka\KafkaOptions;

$container->set(KafkaOptions::class)
    ->args([[
        'brokers' => $_ENV['KAFKA_BROKERS'] ?? 'kafka:9092',
        'consumer' => ['auto_commit' => true, 'auto_offset_reset' => 'earliest'],
        'producer' => ['acks' => 'all', 'compression.type' => 'lz4'],
        'retry' => ['max_attempts' => 3, 'backoff_ms' => 500],
        'discovery' => [
            'namespaces' => ['App\\Kafka\\Handlers\\'],
            'paths' => [__DIR__ . '/../../src/Kafka/Handlers'],
        ],
    ]]);