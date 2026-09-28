<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\UpdateSubscriptionData;
use Andriichuk\LaravelBilling\Enums\CancellationMode;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
interface ManagesSubscriptions { public function createSubscription(CreateSubscriptionData $data): SubscriptionData; public function updateSubscription(SubscriptionReference $subscription, UpdateSubscriptionData $data): SubscriptionData; public function cancelSubscription(SubscriptionReference $subscription, CancellationMode $mode): SubscriptionData; public function retrieveSubscription(SubscriptionReference $subscription): SubscriptionData; }
