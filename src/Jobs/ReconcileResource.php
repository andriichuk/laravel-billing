<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Jobs;

use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Support\ReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ReconcileResource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 900;

    public function __construct(public readonly string $driver, public readonly ReconciliationRequest $request) {}

    public function uniqueId(): string
    {
        return $this->driver.':'.($this->request->model ?? '*').':'.($this->request->id ?? '*');
    }

    public function handle(ReconciliationService $service): void
    {
        $service->run($this->driver, $this->request);
    }
}
