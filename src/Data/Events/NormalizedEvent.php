<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Data\Events;
interface NormalizedEvent { public function providerResourceId(): string; /** @return array<string,mixed> */ public function data(): array; }
