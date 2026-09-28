<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Testing;

use Andriichuk\LaravelBilling\Contracts\ManagesSubscriptions;
use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\UpdateSubscriptionData;
use Andriichuk\LaravelBilling\Enums\CancellationMode;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Exceptions\BillingResourceNotFound;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use DateTimeImmutable;

final class FakeSubscriptionGateway implements ManagesSubscriptions
{
    public function __construct(private readonly FakeStore $store) {}

    public function createSubscription(CreateSubscriptionData $data): SubscriptionData
    {
        $this->store->record('createSubscription', $data);
        $id = 'sub_fake_'.(++$this->store->subscriptionSequence);
        $trial = $data->trialDays === null ? null : new DateTimeImmutable("+{$data->trialDays} days");
        $status = $data->trialDays !== null && $data->trialDays > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active;

        return $this->store->subscriptions[$id] = new SubscriptionData(new SubscriptionReference($id), $data->type, $status, $data->customer?->id, priceId: $data->price, quantity: $data->quantity, trialEndsAt: $trial, providerData: ['fake' => true], metadata: $data->metadata);
    }

    public function updateSubscription(SubscriptionReference $subscription, UpdateSubscriptionData $data): SubscriptionData
    {
        $this->store->record('updateSubscription', $data);
        $old = $this->find($subscription);

        return $this->store->subscriptions[$subscription->id] = new SubscriptionData($subscription, $old->type, $old->status, $old->customerId, $old->productId, $data->price ?? $old->priceId, $data->quantity ?? $old->quantity, $old->recurringAmount, $old->billingInterval, $old->billingIntervalCount, $old->autoRenew, $old->trialEndsAt, $old->nextChargeAt, $old->endsAt, $old->pausedAt, $old->rawProviderData(), array_replace($old->metadata, $data->metadata));
    }

    public function cancelSubscription(SubscriptionReference $subscription, CancellationMode $mode): SubscriptionData
    {
        $this->store->record('cancelSubscription', ['subscription' => $subscription, 'mode' => $mode]);
        $old = $this->find($subscription);
        $ends = $mode === CancellationMode::AtPeriodEnd ? new DateTimeImmutable('+1 month') : new DateTimeImmutable;

        return $this->store->subscriptions[$subscription->id] = new SubscriptionData($subscription, $old->type, SubscriptionStatus::Canceled, $old->customerId, $old->productId, $old->priceId, $old->quantity, $old->recurringAmount, $old->billingInterval, $old->billingIntervalCount, false, $old->trialEndsAt, $old->nextChargeAt, $ends, $old->pausedAt, $old->rawProviderData(), $old->metadata);
    }

    public function retrieveSubscription(SubscriptionReference $subscription): SubscriptionData
    {
        $this->store->record('retrieveSubscription', $subscription);

        return $this->find($subscription);
    }

    private function find(SubscriptionReference $subscription): SubscriptionData
    {
        return $this->store->subscriptions[$subscription->id] ?? throw new BillingResourceNotFound("Fake subscription [{$subscription->id}] was not found.");
    }
}
