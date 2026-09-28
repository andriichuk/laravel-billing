<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Builders;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ManagesSubscriptions;
use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\InvalidBillingPayload;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;
use Illuminate\Database\Eloquent\Model;

final class SubscriptionBuilder
{
    private ?string $driverName = null;
    private int $quantity = 1;
    private ?int $trialDays = null;
    private ?PaymentMethodReference $paymentMethod = null;
    /** @var array<string,mixed> */ private array $metadata = [];
    /** @var array<string,mixed> */ private array $providerOptions = [];
    private ?string $idempotencyKey = null;

    public function __construct(private readonly Model $billable, private readonly string $type, private readonly string $price)
    {
        if (! $billable->exists) { throw InvalidBillingPayload::because('The billable model must be persisted before subscribing.'); }
        if (trim($type) === '' || trim($price) === '') { throw InvalidBillingPayload::because('Subscription type and price cannot be empty.'); }
    }
    public function driver(string $driver): self { if (trim($driver) === '') { throw InvalidBillingPayload::because('Driver name cannot be empty.'); } $this->driverName = $driver; return $this; }
    public function quantity(int $quantity): self { if ($quantity < 1) { throw InvalidBillingPayload::because('Subscription quantity must be at least 1.'); } $this->quantity = $quantity; return $this; }
    public function trialDays(int $days): self { if ($days < 0) { throw InvalidBillingPayload::because('Trial days cannot be negative.'); } $this->trialDays = $days; return $this; }
    public function paymentMethod(PaymentMethodReference|string $paymentMethod): self { $this->paymentMethod = is_string($paymentMethod) ? new PaymentMethodReference($paymentMethod) : $paymentMethod; return $this; }
    /** @param array<string,mixed> $metadata */ public function withMetadata(array $metadata): self { $this->metadata = $metadata; return $this; }
    /** @param array<string,mixed> $options */ public function withProviderOptions(array $options): self { $this->providerOptions = $options; return $this; }
    public function idempotencyKey(string $key): self { $this->idempotencyKey = $key; return $this; }

    public function create(): Subscription
    {
        $manager = app(BillingManager::class);
        $driver = $manager->require(Capability::Subscriptions, $this->driverName);
        if (! $driver instanceof ManagesSubscriptions) { throw UnsupportedCapability::for($driver, Capability::Subscriptions); }
        if ($this->trialDays !== null && ! $driver->supports(Capability::SubscriptionTrials)) { throw UnsupportedCapability::for($driver, Capability::SubscriptionTrials); }
        $key = $this->billable->getKey();
        if (! is_int($key) && ! is_string($key)) { throw InvalidBillingPayload::because('The billable model must have a scalar key.'); }
        $driverName = $driver->name();
        $existing = $this->billable->morphMany($this->subscriptionModel(), 'billable')->where('driver', $driverName)->where('type', $this->type)->first();
        if ($existing !== null) { throw InvalidBillingPayload::because("A [{$this->type}] subscription already exists for driver [{$driverName}]."); }
        $customer = method_exists($this->billable, 'billingCustomer') ? $this->billable->billingCustomer($driverName) : null;
        $result = $driver->createSubscription(new CreateSubscriptionData($this->billable->getMorphClass(), (string) $key, $this->type, $this->price, $this->quantity, $this->trialDays, $customer === null ? null : new CustomerReference((string) $customer->provider_customer_id), $this->paymentMethod, $this->metadata, $this->providerOptions, $this->idempotencyKey));
        return app(BillingSynchronizer::class)->subscription($this->billable, $driverName, $result);
    }

    /** @return class-string<Subscription> */
    private function subscriptionModel(): string
    {
        $class = config('billing.models.subscription', Subscription::class);
        if (! is_string($class) || ! is_a($class, Subscription::class, true)) { throw new \LogicException('Configured subscription model must extend '.Subscription::class.'.'); }
        return $class;
    }
}
