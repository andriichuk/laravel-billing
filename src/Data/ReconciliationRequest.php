<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Data;
final readonly class ReconciliationRequest { public function __construct(public ?string $model = null, public string|int|null $id = null, public bool $dryRun = false) {} }
