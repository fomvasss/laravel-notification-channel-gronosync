<?php

declare(strict_types=1);

namespace NotificationChannels\ItsChats;

use Illuminate\Support\ServiceProvider;

class ItsChatsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ItsChatsApi::class, function ($app) {
            $config = $app['config']['services.itschats'] ?? [];

            return new ItsChatsApi(
                baseUrl: $config['url'] ?? '',
                token: $config['token'] ?? '',
                config: $config,
            );
        });
    }

    public function provides(): array
    {
        return [
            ItsChatsApi::class,
        ];
    }
}
