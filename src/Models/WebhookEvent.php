<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Models;

use Andriichuk\LaravelBilling\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $driver
 * @property string $event_key
 * @property string $event_type
 * @property WebhookStatus $status
 * @property int $attempts
 * @property array<string, mixed> $payload
 * @property string|null $error
 */
class WebhookEvent extends Model
{
    protected $table = 'billing_webhook_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => WebhookStatus::class, 'attempts' => 'integer', 'payload' => 'array', 'received_at' => 'immutable_datetime', 'queued_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime'];
    }
}
