<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
interface SupportsPlanChanges { /** @param array<string,mixed> $providerOptions */ public function changePlan(SubscriptionReference $subscription, string $price, array $providerOptions = []): SubscriptionData; }
