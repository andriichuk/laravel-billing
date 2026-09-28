<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;

interface ReconcilesResources
{
    /**
     * @return iterable<ReconciliationResult>
     */
    public function reconcile(ReconciliationRequest $request): iterable;
}
