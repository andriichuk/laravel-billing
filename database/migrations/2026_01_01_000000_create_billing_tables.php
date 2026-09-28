<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table): void { $table->id(); $table->morphs('billable'); $table->string('driver'); $table->string('provider_customer_id'); $table->string('name')->nullable(); $table->string('email')->nullable(); $table->timestamp('trial_ends_at')->nullable(); $table->json('provider_data')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->unique(['driver', 'provider_customer_id']); $table->unique(['billable_type', 'billable_id', 'driver']); });
        Schema::create('billing_subscriptions', function (Blueprint $table): void { $table->id(); $table->morphs('billable'); $table->string('driver'); $table->string('type'); $table->string('provider_subscription_id'); $table->string('provider_customer_id')->nullable(); $table->string('provider_product_id')->nullable(); $table->string('provider_price_id')->nullable(); $table->string('status'); $table->unsignedInteger('quantity')->default(1); $table->char('currency', 3)->nullable(); $table->string('recurring_amount')->nullable(); $table->string('billing_interval')->nullable(); $table->unsignedInteger('billing_interval_count')->nullable(); $table->boolean('auto_renew')->default(true); $table->timestamp('trial_ends_at')->nullable(); $table->timestamp('next_charge_at')->nullable(); $table->timestamp('ends_at')->nullable(); $table->timestamp('paused_at')->nullable(); $table->json('provider_data')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->unique(['driver', 'provider_subscription_id']); $table->unique(['billable_type', 'billable_id', 'driver', 'type'], 'billing_subscriptions_billable_driver_type_unique'); });
        Schema::create('billing_transactions', function (Blueprint $table): void { $table->id(); $table->morphs('billable'); $table->string('driver'); $table->string('provider_transaction_id'); $table->string('provider_subscription_id')->nullable(); $table->string('type')->nullable(); $table->string('status'); $table->string('amount')->nullable(); $table->char('currency', 3)->nullable(); $table->timestamp('billed_at')->nullable(); $table->json('provider_data')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->unique(['driver', 'provider_transaction_id']); });
        Schema::create('billing_webhook_events', function (Blueprint $table): void { $table->id(); $table->string('driver'); $table->string('event_key'); $table->string('event_type'); $table->string('provider_resource_id')->nullable(); $table->string('status'); $table->unsignedInteger('attempts')->default(0); $table->json('payload'); $table->timestamp('received_at'); $table->timestamp('queued_at')->nullable(); $table->timestamp('processed_at')->nullable(); $table->timestamp('failed_at')->nullable(); $table->text('error')->nullable(); $table->timestamps(); $table->unique(['driver', 'event_key']); $table->index(['status', 'received_at']); });
    }
    public function down(): void { Schema::dropIfExists('billing_webhook_events'); Schema::dropIfExists('billing_transactions'); Schema::dropIfExists('billing_subscriptions'); Schema::dropIfExists('billing_customers'); }
};
