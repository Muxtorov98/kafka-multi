## compose install

```bash
composer require muxtorov98/kafka-multi
```
---
---

## ⚙️ LARAVEL Kafka Configuration (`config/kafka.php`)

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
        'namespaces' => ['App\\Kafka\\Handlers\\'],
        'paths' => [base_path('app/Kafka/Handlers')],
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

### `app/Kafka/Handlers/OrderHistoryHandler.php`

```php
<?php

namespace App\Kafka\Handlers;

use Muxtorov98\Kafka\Attributes\KafkaChannel;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use Muxtorov98\Kafka\DTO\Message;

#[KafkaChannel(topic: 'order-created', group: 'order-laravel')]
class OrderCreatedHandler implements KafkaHandlerInterface
{
    public function handle(Message|array $message): void
    {
        echo "🔥 New order received laravel: " . json_encode($message->payload) . PHP_EOL;
    }
}

```

---
---

## 🚀 Worker Ishga Tushirish

```bash
 php artisan kafka:work
```

**Avtomatik aniqlash va forking:**

```
[INFO] Starting Kafka Workers...


Topic: order-created (group=order-laravel, concurrency=1)
  [WORKER-01] PID=2429 started

[OK] All workers are ready & listening...

Topic: order-created (group=order-laravel, concurrency=1)
[OK] All workers are ready & listening...

  [WORKER-01] PID=1699 started

```

---

## 📨 Xabar Yuborish (Publish)

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Muxtorov98\Kafka\KafkaPublisher;

class KafkaPublishCommand extends Command
{
    protected $signature = 'kafka:publish {topic} {json}';
    protected $description = 'Publish a Kafka message';

    public function handle(KafkaPublisher $publisher): int
    {
        $topic = $this->argument('topic');
        $payload = json_decode($this->argument('json'), true);

        if (!is_array($payload)) {
            $this->error("Invalid JSON payload.");
            return self::FAILURE;
        }

        $publisher->send($topic, $payload);
        $this->info("✅ Message sent to '{$topic}'");

        return self::SUCCESS;
    }
}

```
📤 Single
```bash

 php artisan kafka:publish order-created '{"id":1,"name":"Test"}'

```
**Natija:**

```
✅ Message sent to topic "order-created"
```

---

## 🧩 Natijaviy Ishlash

Yuborilgan bitta xabar **uchta** handler tomonidan qayta ishlanadi:

```
[INFO] Starting Kafka Workers...


Topic: order-created (group=order-laravel, concurrency=1)
  [WORKER-01] PID=2429 started

[OK] All workers are ready & listening...

Topic: order-created (group=order-laravel, concurrency=1)
[OK] All workers are ready & listening...

  [WORKER-01] PID=1699 started
🔥 New order received laravel: {"id":1,"name":"Test"}


```

## 📜 License

MIT License © [Muxtorov98](https://github.com/muxtorov98)
