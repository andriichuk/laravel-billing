<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
use Andriichuk\LaravelBilling\Enums\Capability;
interface BillingDriver
{
    public function name(): string;
    /** @return list<Capability> */
    public function capabilities(): array;
    public function supports(Capability $capability): bool;
}
