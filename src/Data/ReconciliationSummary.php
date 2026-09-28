<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

final readonly class ReconciliationSummary
{
    public function __construct(
        public int $reconciled = 0,
        public int $unchanged = 0,
        public int $skippedUnresolvable = 0,
    ) {}

    public function total(): int
    {
        return $this->reconciled + $this->unchanged + $this->skippedUnresolvable;
    }
}
