<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Installer;

use Composer\Script\Event;

final class ComposerInstaller
{
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();
        $cwd = getcwd();

        $framework = self::detectFramework($cwd);

        if (!$framework) {
            $io->write("<comment>[Kafka-Multi]</comment> ⚠️ Framework topilmadi. Manual config yaratishingiz kerak.");
            return;
        }

        $io->write("<info>[Kafka-Multi]</info> {$framework} aniqlandi.");

        $question = match ($framework) {
            'Yii2' => "Yii2 uchun kafka config yaratilsinmi? (Y/n)",
            'Laravel' => "Laravel uchun kafka config publish qilinsinmi? (Y/n)",
            'Symfony' => "Symfony uchun kafka config generate qilinsinmi? (Y/n)",
        };

        if (!$io->askConfirmation("  $question ", true)) {
            $io->write("  ❌ O‘tkazib yuborildi. Keyin yaratish uchun command:");
            $io->write(match ($framework) {
                'Yii2' => "     php yii kafka:install",
                'Laravel' => "     php artisan kafka:install",
                'Symfony' => "     php bin/console kafka:install",
            });
            return;
        }

        match ($framework) {
            'Yii2' => self::installYii2($io, $cwd),
            'Laravel' => self::installLaravel($io, $cwd),
            'Symfony' => self::installSymfony($io, $cwd),
        };
    }

    private static function detectFramework(string $path): ?string
    {
        return match (true) {
            file_exists($path . '/yii') => 'Yii2',
            file_exists($path . '/artisan') => 'Laravel',
            file_exists($path . '/bin/console') => 'Symfony',
            default => null
        };
    }

    private static function installYii2($io, string $basePath): void
    {
        $configPath = $basePath . '/common/config/kafka.php';

        if (file_exists($configPath)) {
            $io->write("  ⚠️ common/config/kafka.php allaqachon mavjud, o‘tkazib yuborildi.");
            return;
        }

        $content = self::getDefaultConfig('Yii2');

        file_put_contents($configPath, $content);
        $io->write("  ✅ Yaratildi: common/config/kafka.php");

        // controllerMap auto add (optional future)
        // $io->write("  🔗 ControllerMap qo‘shing: 'kafka' => Muxtorov98\\Kafka\\Bridge\\Yii2\\Controllers\\KafkaController::class");
    }

    private static function installLaravel($io, string $basePath): void
    {
        $configPath = $basePath . '/config/kafka.php';

        if (file_exists($configPath)) {
            $io->write("  ⚠️ config/kafka.php mavjud, o‘tkazib yuborildi.");
            return;
        }

        $content = self::getDefaultConfig('Laravel');
        file_put_contents($configPath, $content);

        $io->write("  ✅ Yaratildi: config/kafka.php");
    }

    private static function installSymfony($io, string $basePath): void
    {
        $dir = $basePath . '/config/packages';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $configPath = $dir . '/kafka.php';

        if (file_exists($configPath)) {
            $io->write("  ⚠️ config/packages/kafka.php mavjud, o‘tkazib yuborildi.");
            return;
        }

        $content = self::getDefaultConfig('Symfony');
        file_put_contents($configPath, $content);

        $io->write("  ✅ Yaratildi: config/packages/kafka.php");
    }

    private static function getDefaultConfig(string $framework): string
    {
        // Frameworkga qarab differensial discovery sozlamalarini tayyorlaymiz
        $discoveryNamespaces = match ($framework) {
            'Yii2'     => "['common\\\\kafka\\\\handlers\\\\']",
            'Laravel'  => "['App\\\\Kafka\\\\Handlers\\\\']",
            'Symfony'  => "['App\\\\Kafka\\\\Handlers\\\\']",
            default    => "[]"
        };

        $discoveryPaths = match ($framework) {
            'Yii2'     => "[Yii::getAlias('@common/kafka/handlers')]",
            'Laravel'  => "[base_path('app/Kafka/Handlers')]",
            'Symfony'  => "[dirname(__DIR__) . '/src/Kafka/Handlers']",
            default    => "[]"
        };

        return <<<PHP
<?php

/**
 * Kafka configuration
 *
 * ⚠️ Two types of configs exist here:
 * 1) Kafka native config (inside 'kafka' arrays) → passed directly to librdkafka
 * 2) Custom config (batch, retry, discovery, middlewares) → used by this package only
 *
 * More docs: https://github.com/muxtorov98/kafka-multi
 */

return [

    /**
     * Kafka brokers list (multiple allowed: "k1:9092,k2:9092,k3:9092")
     */
    'brokers' => 'kafka:9092',

    'consumer' => [

        /**
         * ✅ Kafka native consumer config (librdkafka)
         * These keys are passed as: \$conf->set(key, value)
         */
        'kafka' => [
            'enable.auto.commit' => 'true',     // Auto commit offsets
            'auto.offset.reset'  => 'earliest', // Start from earliest if no committed offset
        ],

        /**
         * 🧩 Custom consumer config (for this package)
         */
        'batch_size' => 1,   // How many messages are consumed per batch
        'middlewares' => [
            // Example: \\App\\Kafka\\Middlewares\\LoggingMiddleware::class
        ],
    ],

    'producer' => [

        /**
         * ✅ Kafka native producer config (librdkafka)
         */
        'kafka' => [
            'acks'             => 'all', // safest mode: wait for all replicas
            'compression.type' => 'lz4', // faster + smaller payload
            'linger.ms'        => '1',   // batching allowance for performance
        ],

        /**
         * 🧩 Custom producer config (for this package)
         */
        'middlewares' => [
            // Example: \\App\\Kafka\\Middlewares\\ProducerEncryptMiddleware::class
        ],
    ],

    /**
     * 🔁 Retry strategy for consumer message failures
     */
    'retry' => [
        'max_attempts' => 3,      // If handler fails N times → message goes to DLQ
        'backoff_ms'   => 500,    // Wait time between retries
        'dlq_suffix'   => '-dlq', // Suffix added to topic name for DLQ
    ],

    /**
     * 🧠 Handler auto-discovery locations
     */
    'discovery' => [
        'namespaces' => {$discoveryNamespaces},
        'paths'      => {$discoveryPaths},
    ],

    /**
     * 🔐 Kafka Security (SASL / SSL)
     * Only configure if broker requires authentication
     */
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
PHP;
    }
}