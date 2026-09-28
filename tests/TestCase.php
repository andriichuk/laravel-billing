<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Tests;

use Andriichuk\LaravelBilling\BillingServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BillingServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /** @param array<string, mixed> $parameters */
    protected function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        if (is_int($pending)) {
            throw new RuntimeException("Artisan command [{$command}] ran without a pending command instance.");
        }

        return $pending;
    }
}
