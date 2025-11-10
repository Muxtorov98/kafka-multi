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
        if (!$app instanceof \yii\console\Application) {
            return;
        }

        $configPath = '@common/config/kafka.php';
        $cfg = is_file(Yii::getAlias($configPath)) ? require Yii::getAlias($configPath) : [];

        $options = KafkaOptions::fromArray($cfg);

        Yii::$container->set(KafkaOptions::class, $options);
        Yii::$container->set(Producer::class, fn() => new Producer($options));

        // auto-map controller
        $app->controllerMap['kafka'] = \Muxtorov98\Kafka\Bridge\Yii2\Controllers\KafkaController::class;
    }
}