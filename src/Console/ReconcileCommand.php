<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Console;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Support\ReconciliationService;
use Illuminate\Console\Command;
use Throwable;

final class ReconcileCommand extends Command
{
    protected $signature = 'billing:reconcile {--driver=} {--model=} {--id=} {--dry-run}';

    protected $description = 'Reconcile local billing projections with provider state';

    public function handle(BillingManager $billing, ReconciliationService $service): int
    {
        $driver = $this->option('driver');
        $driver = is_string($driver) && $driver !== '' ? $driver : $billing->defaultDriver();
        $model = $this->option('model');
        $model = is_string($model) && $model !== '' ? $model : null;
        if ($model !== null && ! in_array($model, ['customer', 'subscription', 'transaction'], true)) {
            $this->components->error('Model must be customer, subscription, or transaction.');

            return self::INVALID;
        }
        $id = $this->option('id');
        $id = is_string($id) && $id !== '' ? $id : null;
        $dryRun = (bool) $this->option('dry-run');
        try {
            $count = $service->run($driver, new ReconciliationRequest($model, $id, $dryRun), function (ReconciliationResult $result, bool $dry): void {
                $this->line(sprintf('%s %s%s', $dry ? 'Would reconcile' : 'Reconciled', $result->model, $result->resource->reference->id));
            });
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info("{$count} resource(s) ".($dryRun ? 'inspected.' : 'reconciled.'));

        return self::SUCCESS;
    }
}
