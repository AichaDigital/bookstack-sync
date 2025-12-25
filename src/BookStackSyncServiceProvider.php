<?php

declare(strict_types=1);

namespace AichaDigital\BookStackSync;

use AichaDigital\BookStackSync\Api\BookStackClient;
use AichaDigital\BookStackSync\Commands\BookStackDbCommand;
use AichaDigital\BookStackSync\Commands\BookStackExportCommand;
use AichaDigital\BookStackSync\Commands\BookStackPullCommand;
use AichaDigital\BookStackSync\Commands\BookStackPushCommand;
use AichaDigital\BookStackSync\Commands\BookStackSearchCommand;
use AichaDigital\BookStackSync\Commands\BookStackStatusCommand;
use AichaDigital\BookStackSync\Commands\BookStackSyncCommand;
use AichaDigital\BookStackSync\Contracts\BookStackClientInterface;
use AichaDigital\BookStackSync\Database\Database;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BookStackSyncServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('bookstack-sync')
            ->hasConfigFile()
            ->hasCommands([
                BookStackStatusCommand::class,
                BookStackPushCommand::class,
                BookStackPullCommand::class,
                BookStackExportCommand::class,
                BookStackSearchCommand::class,
                BookStackSyncCommand::class,
                BookStackDbCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register the API client interface binding
        $this->app->bind(BookStackClientInterface::class, function () {
            return new BookStackClient(
                config('bookstack-sync.api.url', ''),
                config('bookstack-sync.api.token_id', ''),
                config('bookstack-sync.api.token_secret', ''),
                (int) config('bookstack-sync.api.timeout', 30),
                (bool) config('bookstack-sync.api.verify_ssl', true)
            );
        });

        // Register the Database class as singleton (if enabled)
        $this->app->singleton(Database::class, function () {
            if (! config('bookstack-sync.database.enabled', true)) {
                return null;
            }

            $path = config('bookstack-sync.database.path', 'bookstack-sync.sqlite');

            // If path is relative, prepend storage_path
            if (! str_starts_with($path, '/')) {
                $path = storage_path($path);
            }

            return new Database($path);
        });

        // Register the main BookStackSync class as singleton
        $this->app->singleton(BookStackSync::class, function ($app) {
            $database = null;
            if (config('bookstack-sync.database.enabled', true)) {
                try {
                    $database = $app->make(Database::class);
                } catch (\Throwable) {
                    // Database not available, continue without it
                }
            }

            return new BookStackSync(
                $app->make(BookStackClientInterface::class),
                $database
            );
        });

        // Alias for convenience
        $this->app->alias(BookStackSync::class, 'bookstack-sync');
        $this->app->alias(Database::class, 'bookstack-db');
    }
}
