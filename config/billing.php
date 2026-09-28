<?php

declare(strict_types=1);

use Andriichuk\LaravelBilling\Models\Customer;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Models\Transaction;
use Andriichuk\LaravelBilling\Models\WebhookEvent;

return [
    'default' => env('BILLING_DRIVER', 'fake'),
    'drivers' => [
        'fake' => ['secret' => env('BILLING_FAKE_WEBHOOK_SECRET', 'fake-secret')],
    ],
    'models' => [
        'customer' => Customer::class,
        'subscription' => Subscription::class,
        'transaction' => Transaction::class,
        'webhook_event' => WebhookEvent::class,
    ],
    'webhooks' => [
        'enabled' => true,
        'path' => 'billing/webhooks/{driver}',
        'middleware' => [],
        'connection' => null,
        'queue' => null,
        'retention_days' => 90,
        'stuck_after_minutes' => 15,
    ],
    'reconciliation' => ['chunk_size' => 100],
];
