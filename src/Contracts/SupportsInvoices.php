<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
interface SupportsInvoices { /** @return list<array<string,mixed>> */ public function invoices(string $providerCustomerId): array; }
