<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Installer;

use Composer\Script\Event;

final class ComposerInstaller
{
    /** RUNNERS (silent) */
    public static function postInstall(Event $event): void { self::run($event, 'install'); }
    public static function postUpdate(Event $event): void  { self::run($event, 'update'); }
    public static function postAutoload(Event $event): void
    {
        // quiet, but leave a tiny trace for debugging
        $fw = self::detectFramework(getcwd());
        if ($fw) { $event->getIO()->write("<info>[Kafka-Multi]</info> autoload → {$fw}"); }
    }
    public static function preRemove(Event $event): void   { self::clean($event); }

    /** DETECT */
    private static function detectFramework(string $base): ?string
    {
        return match (true) {
            is_file($base.'/yii')         => 'Yii2',
            is_file($base.'/artisan')     => 'Laravel',
            is_file($base.'/bin/console') => 'Symfony',
            default                       => null
        };
    }

    /** MAIN (require/update) — NO PROMPTS */
    private static function run(Event $event, string $phase): void
    {
        $io  = $event->getIO();
        $cwd = getcwd();
        $fw  = self::detectFramework($cwd);
        if (!$fw) { $io->write("<comment>[Kafka-Multi]</comment> framework topilmadi ({$phase}), skip."); return; }

        // Always try to install (idempotent)
        match ($fw) {
            'Yii2'    => self::installYii2($io, $cwd),
            'Laravel' => self::installLaravel($io, $cwd),
            'Symfony' => self::installSymfony($io, $cwd),
        };
    }

    /** CLEAN (remove) — FULL (R3) */
    private static function clean(Event $event): void
    {
        $io  = $event->getIO();
        $cwd = getcwd();
        $fw  = self::detectFramework($cwd);
        if (!$fw) { $io->write("<comment>[Kafka-Multi]</comment> framework unknown on remove, skip."); return; }

        match ($fw) {
            'Yii2'    => self::removeYii2($io, $cwd),
            'Laravel' => self::removeLaravel($io, $cwd),
            'Symfony' => self::removeSymfony($io, $cwd),
        };
    }

    /* ======================== YII2 ======================== */

    private static function installYii2($io, string $base): void
    {
        // config
        $config = $base.'/common/config/kafka.php';
        if (!is_file($config)) {
            self::mkdirp(dirname($config));
            file_put_contents($config, self::configFor('Yii2'));
            $io->write("  ✅ Yii2: created common/config/kafka.php");
        }

        // handlers dir
        $handlers = $base.'/common/kafka/Handlers';
        if (!is_dir($handlers)) {
            self::mkdirp($handlers);
            $io->write("  📁 Yii2: created common/kafka/Handlers");
        }
    }

    private static function removeYii2($io, string $base): void
    {
        // config
        self::unlinkQuiet($base.'/common/config/kafka.php', "  🧹 Yii2: removed common/config/kafka.php", $io);
        // handlers (FULL clean)
        self::rmdirQuiet($base.'/common/kafka/Handlers', "  🧹 Yii2: removed common/kafka/Handlers", $io);
    }

    /* ======================== LARAVEL ======================== */

    private static function installLaravel($io, string $base): void
    {
        // config
        $config = $base.'/config/kafka.php';
        if (!is_file($config)) {
            self::mkdirp(dirname($config));
            file_put_contents($config, self::configFor('Laravel'));
            $io->write("  ✅ Laravel: created config/kafka.php");
        }

        // handlers dir
        $handlers = $base.'/app/Kafka/Handlers';
        if (!is_dir($handlers)) {
            self::mkdirp($handlers);
            $io->write("  📁 Laravel: created app/Kafka/Handlers");
        }
    }

    private static function removeLaravel($io, string $base): void
    {
        // config
        self::unlinkQuiet($base.'/config/kafka.php', "  🧹 Laravel: removed config/kafka.php", $io);
        // handlers (FULL clean)
        self::rmdirQuiet($base.'/app/Kafka/Handlers', "  🧹 Laravel: removed app/Kafka/Handlers", $io);
    }

    /* ======================== SYMFONY ======================== */

    private static function installSymfony($io, string $base): void
    {
        // bundle add
        $bundlesFile = $base.'/config/bundles.php';
        if (is_file($bundlesFile)) {
            $c = file_get_contents($bundlesFile);
            $needle = "Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],";
            if (!str_contains((string)$c, $needle)) {
                $insert = "    {$needle}\n";
                $c = preg_replace('/return\s*\[/', "return [\n{$insert}", (string)$c, 1);
                if (is_string($c) && $c !== '') {
                    file_put_contents($bundlesFile, $c);
                    $io->write("  ✅ Symfony: added KafkaBundle to config/bundles.php");
                }
            }
        }

        // config
        $config = $base.'/config/kafka.php';
        if (!is_file($config)) {
            self::mkdirp(dirname($config));
            file_put_contents($config, self::configFor('Symfony'));
            $io->write("  ✅ Symfony: created config/kafka.php");
        }

        // handlers dir
        $handlers = $base.'/src/Kafka/Handlers';
        if (!is_dir($handlers)) {
            self::mkdirp($handlers);
            $io->write("  📁 Symfony: created src/Kafka/Handlers");
        }
    }

    private static function removeSymfony($io, string $base): void
    {
        // config
        self::unlinkQuiet($base.'/config/kafka.php', "  🧹 Symfony: removed config/kafka.php", $io);

        // bundle remove
        $bundles = $base.'/config/bundles.php';
        if (is_file($bundles)) {
            $c = (string)file_get_contents($bundles);
            $c = str_replace("Muxtorov98\\Kafka\\Bridge\\Symfony\\KafkaBundle::class => ['all' => true],", '', $c);
            file_put_contents($bundles, $c);
            $io->write("  🧹 Symfony: removed KafkaBundle from config/bundles.php");
        }

        // handlers (FULL clean)
        self::rmdirQuiet($base.'/src/Kafka/Handlers', "  🧹 Symfony: removed src/Kafka/Handlers", $io);
    }

    /* ======================== HELPERS ======================== */

    private static function mkdirp(string $dir): void
    {
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    }
    private static function unlinkQuiet(string $file, string $msg, $io): void
    {
        if (is_file($file)) { @unlink($file); $io->write($msg); }
    }
    private static function rmdirQuiet(string $dir, string $msg, $io): void
    {
        if (!is_dir($dir)) return;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
        $io->write($msg);
    }

    /* ======================== CONFIG TEMPLATES ======================== */

    private static function configFor(string $fw): string
    {
        $ns = match ($fw) {
            'Yii2'    => "['common\\\\kafka\\\\handlers\\\\']",
            'Laravel' => "['App\\\\Kafka\\\\Handlers\\\\']",
            'Symfony' => "['App\\\\Kafka\\\\Handlers\\\\']",
            default   => "[]"
        };
        $paths = match ($fw) {
            'Yii2'    => "[Yii::getAlias('@common/kafka/Handlers')]",
            'Laravel' => "[base_path('app/Kafka/Handlers')]",
            'Symfony' => "[dirname(__DIR__) . '/src/Kafka/Handlers']",
            default   => "[]"
        };

        return <<<PHP
<?php

/**
 * Kafka configuration
 *
 * 📌 Ikki qatlam:
 * 1) 'kafka' ichidagi native parametrlar → bevosita librdkafka'ga uzatiladi
 * 2) custom parametrlar (middlewares, retry, discovery, DLQ) → faqat paket ichida ishlatiladi
 *
 * 🔎 Commit:
 *  - 'enable.auto.commit' = 'true' → avtomatik offset commit
 *  - Agar manual commit kerak bo'lsa, 'enable.auto.commit' = 'false' qilib qo'ying
 */

return [

    // ✅ Kafka broker(lar)
    'brokers' => getenv('KAFKA_BROKERS') ?: 'kafka:9092',

    // ---------- Consumer ----------
    'consumer' => [
        'kafka' => [
            'enable.auto.commit' => 'true',
            'auto.offset.reset'  => 'earliest',
            // misollar:
            // 'enable.partition.eof' => 'true',
            // 'socket.keepalive.enable' => 'true',
        ],
        'batch_size'  => 1,
        'middlewares' => [
            // \\App\\Kafka\\Middlewares\\LoggingMiddleware::class
        ],
    ],

    // ---------- Producer ----------
    'producer' => [
        'kafka' => [
            'acks'             => 'all',
            'compression.type' => 'lz4',
            'linger.ms'        => '1',
        ],
        'middlewares' => [
            // \\App\\Kafka\\Middlewares\\ProducerEncryptMiddleware::class
        ],
    ],

    // ---------- Retry / DLQ ----------
    'retry' => [
        'max_attempts' => 3,
        'backoff_ms'   => 500,
        'dlq_suffix'   => '-dlq',
    ],

    // ---------- Auto-discovery ----------
    'discovery' => [
        'namespaces' => {$ns},
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