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
        $cfg = require Yii::getAlias('@common/config/kafka.php'); // sizning holatingizga mos
        $options = KafkaOptions::fromArray($cfg);

        Yii::$container->set(KafkaOptions::class, $options);
        Yii::$container->set(Producer::class, fn() => new Producer($options));
    }
}