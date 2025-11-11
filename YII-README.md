## compose install

```bash
composer require muxtorov98/kafka-multi
```

---
---

## ⚙️ YII2 Kafka Configuration (`common/config/kafka.php`)

```php
<?php

return [

    // Kafka brokers (multiple allowed: "k1:9092,k2:9092,k3:9092")
    'brokers' => $_ENV['KAFKA_BROKERS'] ?? 'kafka:9092',

    'consumer' => [

        /**
         * Kafka native configs (will be passed to RdKafka\Conf->set())
         * !!! Faqat Kafka parametrlarini shu yerga yozing
         */
        'kafka' => [
            'enable.auto.commit' => 'true',
            'auto.offset.reset' => 'earliest',
            // optional:
            // 'enable.partition.eof' => 'true',
            // 'socket.keepalive.enable' => 'true',
        ],

        /**
         * Custom consumer config for our package
         * Kafka config emas — bizning kod ichida ishlatiladi
         */
        'batch_size' => 1,        // nechta message bir batchda ko‘rib chiqiladi
        'middlewares' => [
            // \common\kafka\middlewares\LoggingMiddleware::class
        ],
    ],

    'producer' => [

        /**
         * Kafka native producer configs
         */
        'kafka' => [
            'acks' => 'all',
            'compression.type' => 'lz4',
            'linger.ms' => '1',
        ],

        /**
         * Custom producer configs (bizning paket uchun)
         */
        'middlewares' => [
            // \common\kafka\middlewares\ProducerLoggingMiddleware::class
        ],
    ],

    'retry' => [
        'max_attempts' => 3,       // handler xato qilsa necha marta qayta uriniladi
        'backoff_ms'   => 500,     // retry orasidagi kutish
        'dlq_suffix'   => '-dlq',  // DLQ topic nomi uchun qo‘shimcha
    ],

    /**
     * Auto-discovery settings: handlers qayerdan qidiriladi
     */
    'discovery' => [
        'namespaces' => ['common\\kafka\\handlers\\'],
        'paths' => [
            Yii::getAlias('@common/kafka/handlers'),
        ],
    ],
   
    'security' => [
        // 'protocol' => 'SASL_SSL',
        // 'sasl' => [
        //     'mechanism' => 'PLAIN',
        //     'username'  => 'admin',
        //     'password'  => 'secret',
        // ],
        // 'ssl' => [
        //     'ca' => '/etc/ssl/certs/ca.pem',
        // ],
    ],
];

```

---

## ⚙️️ console

### `console/config/main.php`

```php
'kafka' => [
            'class' => \Muxtorov98\Kafka\Bridge\Yii2\Controllers\KafkaController::class,
        ]
```

---

### `common/kafka/handlers/OrderHistoryHandler.php`

```php
<?php

namespace common\kafka\handlers;

use Muxtorov98\Kafka\Attributes\KafkaChannel;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use Muxtorov98\Kafka\DTO\Message;

#[KafkaChannel(
    topic: 'order-created',
    group: 'order-yii',
)]
class OrderCreatedHandler implements KafkaHandlerInterface
{
    public function handle(Message|array $message): void
    {
        echo "🔥 [order-service] New order yii: " . json_encode($message->payload) . PHP_EOL;
    }
}
```

---
---

## 🚀 Worker Ishga Tushirish

```bash
php yii kafka/work
```

**Avtomatik aniqlash va forking:**

```
[INFO] [INFO] Starting Kafka Workers...


Topic: order-created (group=order-service, concurrency=1)
  [WORKER-01] PID=7389 started

[OK] All workers are ready & listening...

Topic: order-created (group=order-service, concurrency=1)
[OK] All workers are ready & listening...

  [WORKER-01] PID=2794 started

```

---

## 📨 Xabar Yuborish (Publish)

```php
<?php
declare(strict_types=1);

namespace console\controllers;

use Muxtorov98\Kafka\Bridge\Yii2\KafkaPublisher;
use yii\console\Controller;
use yii\helpers\Json;

class KafkaPublishController extends Controller
{
    public $defaultAction = 'send';

    public function __construct(
        $id,
        $module,
        private KafkaPublisher $publisher,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Send single message
     * Usage:
     * php yii kafka-publish/send order-created '{"id":1,"status":"created"}'
     */
    public function actionSend(string $topic, string $jsonPayload): void
    {
        $payload = Json::decode($jsonPayload, true);
        $this->publisher->send($topic, $payload);
        $this->stdout("✅ Sent to {$topic}\n");
    }

    /**
     * Send a batch of messages
     * Usage:
     * php yii kafka-publish/batch order-created '[{"id":1},{"id":2}]'
     */
    public function actionBatch(string $topic, string $jsonArray): void
    {
        $messages = Json::decode($jsonArray, true);
        $this->publisher->sendBatch($topic, $messages);
        $this->stdout("✅ Batch sent to {$topic}\n");
    }
}
```
📤 Single
```bash

php yii kafka-publish/send order-created '{"order_id":999}'
```

📦 Batch:
```bash
php yii kafka-publish/batch order-created '[{"id":1},{"id":2}]'

```

**Natija:**

```
✅ Message sent to topic "order-create"
```

---

## 🧩 Natijaviy Ishlash

Yuborilgan bitta xabar **uchta** handler tomonidan qayta ishlanadi:

```
[INFO] [INFO] Starting Kafka Workers...


Topic: order-created (group=order-service, concurrency=1)
  [WORKER-01] PID=7389 started

[OK] All workers are ready & listening...

Topic: order-created (group=order-service, concurrency=1)
[OK] All workers are ready & listening...

  [WORKER-01] PID=2794 started
🔥 [order-service] New order: {"id":1}
🔥 [order-service] New order: {"id":2}

```

## 📜 License

MIT License © [Muxtorov98](https://github.com/muxtorov98)
