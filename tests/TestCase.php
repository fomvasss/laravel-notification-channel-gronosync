<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync\Tests;

use NotificationChannels\Gronosync\GronosyncServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GronosyncServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('services.gronosync', [
            'url'   => 'https://api.gronosync.test',
            'token' => 'test-token-123',
        ]);
    }
}
