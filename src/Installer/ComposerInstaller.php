<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Installer;

use Composer\Script\Event;

final class ComposerInstaller
{
    public static function postInstall(Event $event): void
    {
        self::handle($event);
    }

    public static function postUpdate(Event $event): void
    {
        self::handle($event);
    }

    public static function preRemove(Event $event): void
    {
        self::remove($event);
    }

    private static function handle(Event $event): void
    {
        $io  = $event->getIO();
        $cwd = getcwd();
        $fw  = self::detect($cwd);

        if (!$fw) return;

        match ($fw) {
            'Yii2'    => self::installYii2($cwd),
            'Laravel' => self::installLaravel($cwd),
            'Symfony' => self::installSymfony($cwd),
        };

        $io->write("✔ Kafka-Multi installed for {$fw}");
    }

    private static function remove(Event $event): void
    {
        $io  = $event->getIO();
        $cwd = getcwd();
        $fw  = self::detect($cwd);

        if (!$fw) return;

        match ($fw) {
            'Yii2'    => self::removeYii2($cwd),
            'Laravel' => self::removeLaravel($cwd),
            'Symfony' => self::removeSymfony($cwd),
        };

        $io->write("🧹 Kafka-Multi removed for {$fw}");
    }

    private static function detect(string $path): ?string
    {
        return match (true) {
            file_exists($path . '/yii')        => 'Yii2',
            file_exists($path . '/artisan')    => 'Laravel',
            file_exists($path . '/bin/console') => 'Symfony',
            default => null
        };
    }

    /* -------------------- YII2 -------------------- */

    private static function installYii2(string $base): void
    {
        $config = $base . '/common/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::config('Yii2'));
        }
    }

    private static function removeYii2(string $base): void
    {
        $config = $base . '/common/config/kafka.php';
        if (file_exists($config)) unlink($config);
    }

    /* -------------------- LARAVEL -------------------- */

    private static function installLaravel(string $base): void
    {
        $config = $base . '/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::config('Laravel'));
        }
    }

    private static function removeLaravel(string $base): void
    {
        $config = $base . '/config/kafka.php';
        if (file_exists($config)) unlink($config);
    }

    /* -------------------- SYMFONY -------------------- */

    private static function installSymfony(string $base): void
    {
        $bundleFile = $base . '/config/bundles.php';
        $content = file_get_contents($bundleFile);

        if (!str_contains($content, "KafkaBundle")) {
            $insert = "    Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],\n";
            $content = preg_replace('/return \[/', "return [\n{$insert}", $content);
            file_put_contents($bundleFile, $content);
        }

        $config = $base . '/config/kafka.php';
        if (!file_exists($config)) {
            file_put_contents($config, self::config('Symfony'));
        }
    }

    private static function removeSymfony(string $base): void
    {
        $config = $base . '/config/kafka.php';
        if (file_exists($config)) unlink($config);

        $bundleFile = $base . '/config/bundles.php';
        $content = file_get_contents($bundleFile);
        $content = str_replace("Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],", '', $content);
        file_put_contents($bundleFile, $content);
    }

    /* -------------------- DEFAULT CONFIG -------------------- */

    private static function config(string $fw): string
    {
        $ns = match ($fw) {
            'Yii2'    => "['common\\\\kafka\\\\handlers\\\\']",
            'Laravel' => "['App\\\\Kafka\\\\Handlers\\\\']",
            'Symfony' => "['App\\\\Kafka\\\\Handlers\\\\']",
        };

        $paths = match ($fw) {
            'Yii2'    => "[Yii::getAlias('@common/kafka/handlers')]",
            'Laravel' => "[base_path('app/Kafka/Handlers')]",
            'Symfony' => "[dirname(__DIR__) . '/src/Kafka/Handlers']",
        };

        return <<<PHP
<?php

return [
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
            'acks' => 'all',
            'compression.type' => 'lz4',
            'linger.ms' => '1',
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