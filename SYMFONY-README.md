## config

- config/services.yaml

```yaml
    Muxtorov98\Kafka\KafkaPublisher:
        public: true
```

- config/bundles.php

```php
 Muxtorov98\Kafka\Bridge\Symfony\KafkaBundle::class => ['all' => true],
```

## ⚙️ SYMFONY Kafka Configuration (`config/kafka.php`)

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
        'paths' => [__DIR__ . '/../src/Kafka/Handlers'],
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

## compose install

```bash
composer require muxtorov98/kafka-multi
```

---

### `src/Kafka/Handlers/OrderCreatedHandler.php`

```php
<?php

namespace App\Kafka\Handlers;

use Muxtorov98\Kafka\Attributes\KafkaChannel;
use Muxtorov98\Kafka\Contracts\KafkaHandlerInterface;
use Muxtorov98\Kafka\DTO\Message;

#[KafkaChannel(
    topic: 'order-created',
    group: 'order-symfony'
)]
class OrderCreatedHandler implements KafkaHandlerInterface
{
    public function handle(Message|array $message): void
    {
        echo "🔥 New order received symfony: " . json_encode($message instanceof Message ? $message->payload : $message) . PHP_EOL;
    }
}

```
---
---

## 🚀 Worker Ishga Tushirish

```bash
php bin/console kafka:work
```

**Avtomatik aniqlash va forking:**

```
[INFO] Starting Kafka Workers...

Topic: order-created (group=order-symfony, concurrency=1)
  [WORKER-01] PID=7818 started

[OK] All workers are ready & listening...

Topic: order-created (group=order-symfony, concurrency=1)
[OK] All workers are ready & listening...

  [WORKER-01] PID=2174 started

```

---

## 📨 Xabar Yuborish (Publish)

```php
<?php

namespace App\Command;

use Muxtorov98\Kafka\Bridge\Symfony\KafkaPublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kafka:publish',
    description: 'Publish a message to a Kafka topic'
)]
class KafkaPublishCommand extends Command
{
    public function __construct(
        private KafkaPublisher $publisher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('topic', InputArgument::REQUIRED, 'Kafka topic name')
            ->addArgument('data', InputArgument::REQUIRED, 'JSON encoded message payload');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $topic = $input->getArgument('topic');
        $json  = $input->getArgument('data');

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $output->writeln('<error>❌ Invalid JSON:</error> ' . json_last_error_msg());
            return Command::FAILURE;
        }

        try {
            $this->publisher->send($topic, $data);
        } catch (\Throwable $e) {
            $output->writeln("<error>❌ Failed to publish message:</error> {$e->getMessage()}");
            return Command::FAILURE;
        }

        $output->writeln("✅ Message sent to <info>{$topic}</info>");
        return Command::SUCCESS;
    }
}

```
📤 Single
```bash

php bin/console kafka:publish order-created '{"id":1,"customer":"John"}'
```

**Natija:**

```
✅ Message sent to topic "order-create"
```

---

## 🧩 Natijaviy Ishlash

Yuborilgan bitta xabar **uchta** handler tomonidan qayta ishlanadi:

```
[INFO] Starting Kafka Workers...

Topic: order-created (group=order-symfony, concurrency=1)
  [WORKER-01] PID=7818 started

[OK] All workers are ready & listening...

Topic: order-created (group=order-symfony, concurrency=1)
[OK] All workers are ready & listening...

  [WORKER-01] PID=2174 started
🔥 New order received symfony: {"id":1,"customer":"John"}

```

## 📜 License

MIT License © [Muxtorov98](https://github.com/muxtorov98)
