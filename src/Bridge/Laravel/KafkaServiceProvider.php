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
        $this->publishes([
            __DIR__.'/config/kafka.php' => config_path('kafka.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([Commands\KafkaWorkCommand::class]);
        }
    }
}