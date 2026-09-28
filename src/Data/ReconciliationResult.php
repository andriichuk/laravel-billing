<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Data;

final readonly class ReconciliationResult
{
    public function __construct(
        public string $model,
        public CustomerData|SubscriptionData|TransactionData $resource,
        public bool $changed = false,
        public bool $skippedUnresolvable = false,
        public ?string $message = null,
    ) {}

    public function processed(bool $changed): self
    {
        return new self($this->model, $this->resource, $changed);
    }

    public function skipped(string $message): self
    {
        return new self($this->model, $this->resource, skippedUnresolvable: true, message: $message);
    }
}
