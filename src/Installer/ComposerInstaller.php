<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Installer;

use Composer\Script\Event;

final class ComposerInstaller
{
    public static function postInstall(Event $event): void
    {
        self::run($event, 'install');
    }

    public static function postUpdate(Event $event): void
    {
        self::run($event, 'update');
    }

    public static function postAutoload(Event $event): void
    {
        $io = $event->getIO();
        $framework = self::detectFramework(getcwd());

        if ($framework) {
            $io->write("<info>[Kafka-Multi]</info> Autoload detected → {$framework}");
        }
    }

    public static function preRemove(Event $event): void
    {
        $io = $event->getIO();
        $cwd = getcwd();
        $framework = self::detectFramework($cwd);

        if (!$framework) {
            $io->write("<comment>[Kafka-Multi]</comment> Framework unknown. Skipping cleanup.");
            return;
        }

        match ($framework) {
            'Yii2' => self::removeYii2($io, $cwd),
            'Laravel' => self::removeLaravel($io, $cwd),
            'Symfony' => self::removeSymfony($io, $cwd),
        };
    }

    private static function run(Event $event, string $type): void
    {
        $io = $event->getIO();
        $cwd = getcwd();
        $framework = self::detectFramework($cwd);

        if (!$framework) {
            $io->write("<comment>[Kafka-Multi]</comment> Framework not detected. Skipped.");
            return;
        }

        $question = match ($framework) {
            'Yii2' => "Create kafka.php config for Yii2? (Y/n)",
            'Laravel' => "Publish kafka.php config for Laravel? (Y/n)",
            'Symfony' => "Add KafkaBundle + create config for Symfony? (Y/n)",
        };

        if (!$io->askConfirmation("  $question ", true)) {
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
        $config = $basePath . '/common/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::getDefaultConfig('Yii2'));
            $io->write("  ✅ Created: common/config/kafka.php");
        }

        $handlers = $basePath . '/common/kafka/handlers';
        if (!is_dir($handlers)) {
            mkdir($handlers, 0777, true);
            $io->write("  📁 Created: common/kafka/handlers");
        }
    }

    private static function removeYii2($io, string $basePath): void
    {
        $config = $basePath . '/common/config/kafka.php';
        if (file_exists($config)) {
            unlink($config);
            $io->write("  🧹 Removed: common/config/kafka.php");
        }
    }

    private static function installLaravel($io, string $basePath): void
    {
        $config = $basePath . '/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::getDefaultConfig('Laravel'));
            $io->write("  ✅ Created: config/kafka.php");
        }

        $handlers = $basePath . '/app/Kafka/Handlers';
        if (!is_dir($handlers)) {
            mkdir($handlers, 0777, true);
            $io->write("  📁 Created: app/Kafka/Handlers");
        }
    }

    private static function removeLaravel($io, string $basePath): void
    {
        $config = $basePath . '/config/kafka.php';
        if (file_exists($config)) {
            unlink($config);
            $io->write("  🧹 Removed: config/kafka.php");
        }
    }

    private static function installSymfony($io, string $basePath): void
    {
        $bundles = $basePath . '/config/bundles.php';
        $content = file_get_contents($bundles);

        if (!str_contains($content, "KafkaBundle")) {
            $insert = "    Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],\n";
            $content = preg_replace('/return \\[/','return [' . "\n" . $insert, $content);
            file_put_contents($bundles, $content);
            $io->write("  ✅ Added KafkaBundle to config/bundles.php");
        }

        $config = $basePath . '/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::getDefaultConfig('Symfony'));
            $io->write("  ✅ Created: config/kafka.php");
        }

        $handlers = $basePath . '/src/Kafka/Handlers';
        if (!is_dir($handlers)) {
            mkdir($handlers, 0777, true);
            $io->write("  📁 Created: src/Kafka/Handlers");
        }
    }

    private static function removeSymfony($io, string $basePath): void
    {
        $config = $basePath . '/config/kafka.php';
        if (file_exists($config)) {
            unlink($config);
            $io->write("  🧹 Removed: config/kafka.php");
        }

        $bundles = $basePath . '/config/bundles.php';
        $c = file_get_contents($bundles);
        $c = str_replace("Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],", "", $c);
        file_put_contents($bundles, $c);
        $io->write("  🧹 Removed: KafkaBundle from config/bundles.php");
    }

    private static function getDefaultConfig(string $framework): string
    {
        $ns = match ($framework) {
            'Yii2' => "['common\\\\kafka\\\\handlers\\\\']",
            'Laravel' => "['App\\\\Kafka\\\\Handlers\\\\']",
            'Symfony' => "['App\\\\Kafka\\\\Handlers\\\\']",
        };

        $paths = match ($framework) {
            'Yii2' => "[Yii::getAlias('@common/kafka/handlers')]",
            'Laravel' => "[base_path('app/Kafka/Handlers')]",
            'Symfony' => "[dirname(__DIR__) . '/src/Kafka/Handlers']",
        };

        return <<<PHP
<?php

/**
 * Kafka configuration
 *
 * 📌 Two config levels:
 * ─ Native Kafka config (goes into librdkafka)
 * ─ Package-level custom config (middlewares, retry, discovery, DLQ)
 */

return [

    // ✅ Kafka broker list
    'brokers' => 'kafka:9092',

    'consumer' => [
        'kafka' => [
            'enable.auto.commit' => 'true',
            'auto.offset.reset'  => 'earliest',
        ],
        'batch_size' => 1,
        'middlewares' => [],
    ],

    'producer' => [
        'kafka' => [
            'acks'             => 'all',
            'compression.type' => 'lz4',
            'linger.ms'        => '1',
        ],
        'middlewares' => [],
    ],

    'retry' => [
        'max_attempts' => 3,
        'backoff_ms'   => 500,
        'dlq_suffix'   => '-dlq',
    ],

    'discovery' => [
        'namespaces' => {$ns},
        'paths'      => {$paths},
    ],

    'security' => [],
];
PHP;
    }
}