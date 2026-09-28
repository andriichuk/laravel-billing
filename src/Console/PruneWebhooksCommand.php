<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Console;

use Andriichuk\LaravelBilling\Enums\WebhookStatus;
use Andriichuk\LaravelBilling\Models\WebhookEvent;
use Illuminate\Console\Command;

final class PruneWebhooksCommand extends Command
{
    protected $signature = 'billing:webhooks:prune {--driver=} {--days=} {--dry-run} {--force : Delete without interactive confirmation}';

    protected $description = 'Prune completed billing webhook ledger entries';

    public function handle(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption === null) {
            $configuredDays = config('billing.webhooks.retention_days', 90);
            $days = is_int($configuredDays) ? $configuredDays : filter_var($configuredDays, FILTER_VALIDATE_INT);
        } else {
            $days = filter_var($daysOption, FILTER_VALIDATE_INT);
        }

        if (! is_int($days) || $days < 1) {
            $this->components->error('Retention days must be a positive integer.');

            return self::INVALID;
        }

        $query = WebhookEvent::query()->whereIn('status', [WebhookStatus::Processed, WebhookStatus::Ignored])->where('processed_at', '<', now()->subDays($days));
        $driver = $this->option('driver');

        if (is_string($driver) && $driver !== '') {
            $query->where('driver', $driver);
        }

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->components->info("Would prune {$count} webhook(s).");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently delete {$count} completed webhook ledger entries?", false)) {
            $this->components->warn('Prune cancelled.');

            return self::SUCCESS;
        }

        $deleteResult = $query->delete();
        $deleted = is_int($deleteResult) ? $deleteResult : 0;
        $this->components->info("Pruned {$deleted} webhook(s).");

        return self::SUCCESS;
    }
}
