<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Console;

use Andriichuk\LaravelBilling\Enums\WebhookStatus;
use Andriichuk\LaravelBilling\Jobs\ProcessWebhook;
use Andriichuk\LaravelBilling\Models\WebhookEvent;
use Illuminate\Console\Command;

final class RetryWebhooksCommand extends Command
{
    protected $signature = 'billing:webhooks:retry {--driver=} {--dry-run} {--limit=100}';

    protected $description = 'Requeue failed or stuck billing webhooks';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! is_int($limit) || $limit < 1 || $limit > 10000) {
            $this->components->error('Limit must be between 1 and 10000.');

            return self::INVALID;
        }
        $configuredMinutes = config('billing.webhooks.stuck_after_minutes', 15);
        $minutes = is_int($configuredMinutes) ? $configuredMinutes : filter_var($configuredMinutes, FILTER_VALIDATE_INT);
        $stale = now()->subMinutes(is_int($minutes) ? $minutes : 15);
        $query = WebhookEvent::query()->where(function ($query) use ($stale): void {
            $query->where('status', WebhookStatus::Failed)->orWhere(function ($query) use ($stale): void {
                $query->whereIn('status', [WebhookStatus::Received, WebhookStatus::Queued, WebhookStatus::Processing])->where('updated_at', '<=', $stale);
            });
        });
        $driver = $this->option('driver');
        if (is_string($driver) && $driver !== '') {
            $query->where('driver', $driver);
        }
        $events = $query->oldest('id')->limit($limit)->get();
        foreach ($events as $event) {
            $key = $event->getKey();
            if (! is_int($key) && ! is_string($key)) {
                continue;
            } if (! $this->option('dry-run')) {
                ProcessWebhook::dispatch($key);
                $event->forceFill(['status' => WebhookStatus::Queued, 'queued_at' => now()])->save();
            } $this->line(($this->option('dry-run') ? 'Would retry ' : 'Retried ').$event->driver.':'.$event->event_key);
        }
        $this->components->info($events->count().' webhook(s) selected.');

        return self::SUCCESS;
    }
}
