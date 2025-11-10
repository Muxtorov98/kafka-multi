<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Yii2;

use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;
use Yii;
use yii\base\BootstrapInterface;

final class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        // Only for console context
        if (!$app instanceof \yii\console\Application) {
            return;
        }

        // 1) Load config
        $configFile = Yii::getAlias('@common/config/kafka.php');
        $cfg = is_file($configFile) ? require $configFile : [];

        $options = KafkaOptions::fromArray($cfg);

        // 2) Bind to container
        Yii::$container->set(KafkaOptions::class, $options);
        Yii::$container->set(Producer::class, fn() => new Producer($options));

        // 3) Auto-register 'kafka' controller
        $app->controllerMap['kafka'] = \Muxtorov98\Kafka\Bridge\Yii2\Controllers\KafkaController::class;
    }
}