<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Enums;
enum CancellationMode: string { case AtPeriodEnd = 'at_period_end'; case Immediately = 'immediately'; }
