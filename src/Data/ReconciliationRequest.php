<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ReconciliationRequest
{
    public function __construct(
        public ?string $model = null,
        public string|int|null $id = null,
        public bool $dryRun = false,
        public ?DateTimeImmutable $since = null,
        public ?string $cursor = null,
        public int $pageSize = 100,
        public bool $force = false,
    ) {
        if ($this->pageSize < 1) {
            throw new InvalidArgumentException('The reconciliation page size must be at least one.');
        }
    }
}
