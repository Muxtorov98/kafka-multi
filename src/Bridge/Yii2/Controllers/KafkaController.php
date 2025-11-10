<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Yii2\Controllers;

use yii\console\Controller;
use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;
use Muxtorov98\Kafka\Support\WorkerPrinter;

final class KafkaController extends Controller
{
    public function actionWork(): int
    {
        /** @var KafkaOptions $options */
        $options = \Yii::$container->get(KafkaOptions::class);

        /** @var Producer $producer */
        $producer = \Yii::$container->get(Producer::class);

        $routing = AutoDiscovery::discover($options);

        if (empty($routing)) {
            WorkerPrinter::warning("No Kafka handlers found.");
            return self::EXIT_CODE_NORMAL;
        }

        WorkerPrinter::info("Starting Kafka Workers...\n");

        foreach ($routing as $topic => $handlers) {
            foreach ($handlers as $meta) {
                $group = $meta['group'] ?? 'default-group';
                $concurrency = (int)$meta['concurrency'];

                WorkerPrinter::topicHeader($topic, $group, $concurrency);

                for ($i = 1; $i <= $concurrency; $i++) {
                    $pid = random_int(1000, 9999);
                    WorkerPrinter::workerStart($topic, $group, $i, $pid);
                }
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