<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Yii2;

use yii\console\Controller;
use Muxtorov98\Kafka\AutoDiscovery;
use Muxtorov98\Kafka\Consumer;
use Muxtorov98\Kafka\KafkaOptions;
use Yii;

final class WorkerController extends Controller
{
    public $defaultAction = 'start';

    public function actionStart(): int
    {
        /** @var KafkaOptions $options */
        $options = Yii::$container->get(KafkaOptions::class);
        $routing = AutoDiscovery::discover($options);
        $consumer = new Consumer($options, $routing);
        return $consumer->run();
    }
}