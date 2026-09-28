<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling;

use Andriichuk\LaravelBilling\Console\PruneWebhooksCommand;
use Andriichuk\LaravelBilling\Console\ReconcileCommand;
use Andriichuk\LaravelBilling\Console\RetryWebhooksCommand;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\Support\ReconciliationService;
use Andriichuk\LaravelBilling\Testing\FakeDriver;
use Andriichuk\LaravelBilling\Webhooks\WebhookHandlerRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/billing.php', 'billing');
        $this->app->singleton(BillingManager::class, function (Application $app): BillingManager {
            $manager = new BillingManager($app);
            $manager->extend('fake', function (Container $container, array $config): FakeDriver {
                $secret = is_string($config['secret'] ?? null) ? $config['secret'] : 'fake-secret';

                return new FakeDriver('fake', webhookSecret: $secret);
            });

            return $manager;
        });
        $this->app->alias(BillingManager::class, 'billing');
        $this->app->singleton(BillingSynchronizer::class);
        $this->app->singleton(ReconciliationService::class);
        $this->app->singleton(WebhookHandlerRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        if (! $this->app->runningInConsole()) {
            return;
        }
        $this->commands([ReconcileCommand::class, RetryWebhooksCommand::class, PruneWebhooksCommand::class]);
        $this->publishes([__DIR__.'/../config/billing.php' => $this->app->configPath('billing.php')], 'billing-config');
        $this->publishes([__DIR__.'/../database/migrations' => $this->app->databasePath('migrations')], 'billing-migrations');
    }
}
