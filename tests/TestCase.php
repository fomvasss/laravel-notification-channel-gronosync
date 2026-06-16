<?php

declare(strict_types=1);

namespace NotificationChannels\ItsChats\Tests;

use NotificationChannels\ItsChats\ItsChatsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ItsChatsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('services.itschats', [
            'url'   => 'https://itschats.test',
            'token' => 'test-token-123',
        ]);
    }
}
