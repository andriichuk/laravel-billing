<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Console;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Support\ReconciliationService;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Throwable;

final class ReconcileCommand extends Command
{
    protected $signature = 'billing:reconcile
        {--driver= : Billing driver name}
        {--model= : customer, subscription, or transaction}
        {--id= : Target one provider resource ID}
        {--since= : Provider-supported lower bound, as a date/time}
        {--cursor= : Provider cursor to resume after}
        {--page-size=100 : Number of provider resources per page}
        {--dry-run : Inspect changes without writing or dispatching events}
        {--force : Replay lifecycle events even when projections are unchanged}';

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

        if ($id !== null && $model === null) {
            $this->components->error('--id requires --model.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $cursor = $this->option('cursor');
        $cursor = is_string($cursor) && trim($cursor) !== '' ? trim($cursor) : null;
        $pageSize = filter_var($this->option('page-size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($pageSize)) {
            $this->components->error('--page-size must be a positive integer.');

            return self::INVALID;
        }

        try {
            $sinceOption = $this->option('since');
            $since = is_string($sinceOption) && trim($sinceOption) !== '' ? new DateTimeImmutable($sinceOption) : null;
            $summary = $service->run($driver, new ReconciliationRequest(
                model: $model,
                id: $id,
                dryRun: $dryRun,
                since: $since,
                cursor: $cursor,
                pageSize: $pageSize,
                force: $force,
            ), function (ReconciliationResult $result, bool $dry) use ($force): void {
                if ($result->skippedUnresolvable) {
                    $this->line(sprintf('Skipped unresolvable %s %s: %s', $result->model, $result->resource->reference->id, $result->message));

                    return;
                }

                $action = match (true) {
                    $result->changed && $dry => 'Would reconcile',
                    $result->changed => 'Reconciled',
                    $force && ! $dry => 'Replayed unchanged',
                    default => 'Unchanged',
                };
                $this->line(sprintf('%s %s %s', $action, $result->model, $result->resource->reference->id));
            });
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Reconciled: %d; unchanged: %d; skipped-unresolvable: %d.',
            $summary->reconciled,
            $summary->unchanged,
            $summary->skippedUnresolvable,
        ));

        return self::SUCCESS;
    }
}
