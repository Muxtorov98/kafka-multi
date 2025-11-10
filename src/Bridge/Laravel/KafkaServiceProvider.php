<?php
declare(strict_types=1);

namespace Muxtorov98\Kafka\Bridge\Laravel;

use Illuminate\Support\ServiceProvider;
use Muxtorov98\Kafka\KafkaOptions;
use Muxtorov98\Kafka\Producer;

final class KafkaServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Package config merge
        $this->mergeConfigFrom(__DIR__.'/config/kafka.php', 'kafka');

        $this->app->singleton(KafkaOptions::class, function ($app) {
            return KafkaOptions::fromArray(config('kafka', []));
        });

        $this->app->singleton(Producer::class, function ($app) {
            return new Producer($app->make(KafkaOptions::class));
        });
    }

    public function boot()
    {
        // Allow publishing config to project config/kafka.php
        $this->publishes([
            __DIR__.'/config/kafka.php' => config_path('kafka.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {

            // ❗ If project already has user's custom KafkaWorkCommand, DO NOT load package command
            if (!class_exists(\App\Console\Commands\KafkaWorkCommand::class)) {
                $this->commands([
                    \Muxtorov98\Kafka\Bridge\Laravel\Commands\KafkaWorkCommand::class
                ]);
            }
        }
    }
}