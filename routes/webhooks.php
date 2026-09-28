<?php
declare(strict_types=1);
use Andriichuk\LaravelBilling\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;
if (config('billing.webhooks.enabled', true)) { Route::post((string) config('billing.webhooks.path', 'billing/webhooks/{driver}'), WebhookController::class)->middleware(config('billing.webhooks.middleware', []))->name('billing.webhooks'); }
