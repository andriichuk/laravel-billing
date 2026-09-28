<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
interface SupportsQuantityChanges { /** @param array<string,mixed> $providerOptions */ public function changeQuantity(SubscriptionReference $subscription, int $quantity, array $providerOptions = []): SubscriptionData; }
