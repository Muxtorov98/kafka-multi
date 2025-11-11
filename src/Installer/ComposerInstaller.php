<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Installer;

use Composer\Script\Event;

final class ComposerInstaller
{
    public static function postInstall(Event $event): void { self::run($event, 'install'); }
    public static function postUpdate(Event $event): void  { self::run($event, 'update');  }

    public static function postAutoload(Event $event): void
    {
        $io = $event->getIO();
        $fw = self::detectFramework(getcwd());
        if ($fw) $io->write("<info>[Kafka-Multi]</info> Autoload detected → {$fw}");
    }

    public static function preRemove(Event $event): void
    {
        $io  = $event->getIO();
        $cwd = getcwd();
        $fw  = self::detectFramework($cwd);

        if (!$fw) { $io->write("<comment>[Kafka-Multi]</comment> Framework unknown. Skipping cleanup."); return; }

        match ($fw) {
            'Yii2'    => self::removeYii2($io, $cwd),
            'Laravel' => self::removeLaravel($io, $cwd),
            'Symfony' => self::removeSymfony($io, $cwd),
        };
    }

    private static function run(Event $event, string $type): void
    {
        $io  = $event->getIO();
        $cwd = getcwd();
        $fw  = self::detectFramework($cwd);

        if (!$fw) { $io->write("<comment>[Kafka-Multi]</comment> Framework not detected. Skipped."); return; }

        $question = match ($fw) {
            'Yii2'    => "Create kafka.php config for Yii2? (Y/n)",
            'Laravel' => "Create kafka.php config for Laravel? (Y/n)",
            'Symfony' => "Add KafkaBundle + create kafka.php for Symfony? (Y/n)",
        };

        if (!$io->askConfirmation("  $question ", true)) return;

        match ($fw) {
            'Yii2'    => self::installYii2($io, $cwd),
            'Laravel' => self::installLaravel($io, $cwd),
            'Symfony' => self::installSymfony($io, $cwd),
        };
    }

    private static function detectFramework(string $path): ?string
    {
        return match (true) {
            file_exists($path . '/yii')         => 'Yii2',
            file_exists($path . '/artisan')     => 'Laravel',
            file_exists($path . '/bin/console') => 'Symfony',
            default                             => null
        };
    }

    // ---------- Yii2 ----------
    private static function installYii2($io, string $basePath): void
    {
        $config = $basePath . '/common/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::getDefaultConfig('Yii2'));
            $io->write("  ✅ Created: common/config/kafka.php");
        }
        $dir = $basePath . '/common/kafka/handlers';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); $io->write("  📁 Created: common/kafka/handlers"); }
    }
    private static function removeYii2($io, string $basePath): void
    {
        $f = $basePath . '/common/config/kafka.php';
        if (file_exists($f)) { unlink($f); $io->write("  🧹 Removed: common/config/kafka.php"); }
    }

    // ---------- Laravel ----------
    private static function installLaravel($io, string $basePath): void
    {
        $config = $basePath . '/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::getDefaultConfig('Laravel'));
            $io->write("  ✅ Created: config/kafka.php");
        }
        $dir = $basePath . '/app/Kafka/Handlers';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); $io->write("  📁 Created: app/Kafka/Handlers"); }
    }
    private static function removeLaravel($io, string $basePath): void
    {
        $f = $basePath . '/config/kafka.php';
        if (file_exists($f)) { unlink($f); $io->write("  🧹 Removed: config/kafka.php"); }
    }

    // ---------- Symfony ----------
    private static function installSymfony($io, string $basePath): void
    {
        // 1) bundle qo‘shish
        $bundlesFile = $basePath . '/config/bundles.php';
        if (is_file($bundlesFile)) {
            $content = file_get_contents($bundlesFile);
            if (!str_contains($content, "Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle")) {
                $insert = "    Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],\n";
                $content = preg_replace('/return \\[/', "return [\n{$insert}", $content);
                file_put_contents($bundlesFile, $content);
                $io->write("  ✅ Added KafkaBundle to config/bundles.php");
            }
        }

        // 2) config/kafka.php
        $config = $basePath . '/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::getDefaultConfig('Symfony'));
            $io->write("  ✅ Created: config/kafka.php");
        }

        // 3) handlers dir
        $dir = $basePath . '/src/Kafka/Handlers';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); $io->write("  📁 Created: src/Kafka/Handlers"); }
    }
    private static function removeSymfony($io, string $basePath): void
    {
        $f = $basePath . '/config/kafka.php';
        if (file_exists($f)) { unlink($f); $io->write("  🧹 Removed: config/kafka.php"); }

        $bundlesFile = $basePath . '/config/bundles.php';
        if (is_file($bundlesFile)) {
            $c = file_get_contents($bundlesFile);
            $c = str_replace("Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],", "", (string)$c);
            file_put_contents($bundlesFile, $c);
            $io->write("  🧹 Removed: KafkaBundle from config/bundles.php");
        }
    }

    // ---------- Default config (commit doc bilan) ----------
    private static function getDefaultConfig(string $framework): string
    {
        $ns = match ($framework) {
            'Yii2'    => "['common\\\\kafka\\\\handlers\\\\']",
            'Laravel' => "['App\\\\Kafka\\\\Handlers\\\\']",
            'Symfony' => "['App\\\\Kafka\\\\Handlers\\\\']",
        };
        $paths = match ($framework) {
            'Yii2'    => "[Yii::getAlias('@common/kafka/handlers')]",
            'Laravel' => "[base_path('app/Kafka/Handlers')]",
            'Symfony' => "[dirname(__DIR__) . '/src/Kafka/Handlers']",
        };

        return <<<PHP
<?php

/**
 * Kafka configuration
 *
 * 📌 Ikki qatlamli konfiguratsiya:
 * ─ 'kafka' ichidagi native parametrlar to'g'ridan-to'g'ri librdkafka ga beriladi
 * ─ qolgan custom parametrlar (middlewares, retry, discovery, DLQ) faqat paket kodida ishlatiladi
 *
 * 🔎 Commit haqida:
 * 'enable.auto.commit' = true bo'lsa, offsetlar avtomatik commit qilinadi
 * Manual commit rejimi uchun consumer.kafka ichida 'enable.auto.commit' => 'false' qilib, paket manual commitni yoqadi
 */

return [

    // ✅ Kafka brokerlar ro'yxati
    'brokers' => 'kafka:9092',

    // ---------- Consumer ----------
    'consumer' => [
        'kafka' => [
            // 🔁 Offset commit rejimi: true → avtomatik; false → manual (paket commit qiladi)
            'enable.auto.commit' => 'true',
            // 🎯 Yangi group uchun o'qishni qayerdan boshlash
            'auto.offset.reset'  => 'earliest',
        ],
        // Bir batchda nechta xabar iste'mol qilinadi
        'batch_size' => 1,
        // Paket middlewares (logging, metrics va boshqalar)
        'middlewares' => [],
    ],

    // ---------- Producer ----------
    'producer' => [
        'kafka' => [
            // Acknowledgement kafolat darajasi
            'acks'             => 'all',
            // Siqish algoritmi: lz4 (tez + kichik)
            'compression.type' => 'lz4',
            // Batch uchun kichik kechiktirish
            'linger.ms'        => '1',
        ],
        'middlewares' => [],
    ],

    // ---------- Retry / DLQ ----------
    'retry' => [
        // Handler ichida xatolik bo'lsa necha marta qayta uriniladi
        'max_attempts' => 3,
        // Har urinish orasida kutish (ms)
        'backoff_ms'   => 500,
        // DLQ: manba topic nomiga qo'shiladigan suffiks
        'dlq_suffix'   => '-dlq',
    ],

    // ---------- Auto-discovery ----------
    'discovery' => [
        // Handler klasslarining namespace'lari
        'namespaces' => {$ns},
        // Handler fayllari joylashgan yo'llar
        'paths'      => {$paths},
    ],

    // ---------- Security (ixtiyoriy) ----------
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