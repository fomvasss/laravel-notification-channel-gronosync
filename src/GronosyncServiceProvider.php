<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync;

use Illuminate\Support\ServiceProvider;

class GronosyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GronosyncApi::class, function ($app) {
            $config = $app['config']['services.gronosync'] ?? [];

            return new GronosyncApi(
                baseUrl: $config['url'] ?? '',
                token: $config['token'] ?? '',
                config: $config,
            );
        });
    }

    public function provides(): array
    {
        return [
            GronosyncApi::class,
        ];
    }
}
