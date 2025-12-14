<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync\Tests;

use AichaDigital\BookStackSync\BookStackSyncServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'AichaDigital\\BookStackSync\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            BookStackSyncServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // Load test environment from .env if present
        if (file_exists(__DIR__.'/../.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/..');
            $dotenv->safeLoad();
        }

        // Configure BookStack for testing
        config()->set('bookstack-sync.api.url', env('WIKI_URL', 'https://demo.bookstackapp.com'));
        config()->set('bookstack-sync.api.token_id', env('WIKI_TOKEN_ID', ''));
        config()->set('bookstack-sync.api.token_secret', env('WIKI_TOKEN', ''));
    }
}
