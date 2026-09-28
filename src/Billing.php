<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling;
use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Closure;
use Illuminate\Support\Facades\Facade;
/** @method static BillingDriver driver(?string $name = null) @method static BillingManager extend(string $name, Closure $resolver) */
final class Billing extends Facade { protected static function getFacadeAccessor(): string { return BillingManager::class; } }
