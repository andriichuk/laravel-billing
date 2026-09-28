<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Models;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ManagesCustomers;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $driver
 * @property string $provider_customer_id
 * @property string|null $name
 * @property string|null $email
 * @property CarbonImmutable|null $trial_ends_at
 * @property array<string, mixed>|null $provider_data
 * @property array<string, mixed>|null $metadata
 */
class Customer extends Model
{
    protected $table = 'billing_customers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'immutable_datetime', 'provider_data' => 'array', 'metadata' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function syncFromProvider(): self
    {
        $driver = app(BillingManager::class)->require(Capability::Customers, $this->driver);

        if (! $driver instanceof ManagesCustomers) {
            throw UnsupportedCapability::for($driver, Capability::Customers);
        }

        $billable = $this->billable()->first();

        if (! $billable instanceof Model) {
            throw new \LogicException('Billing customer has no billable model.');
        }

        return app(BillingSynchronizer::class)->customer($billable, $this->driver, $driver->retrieveCustomer(new CustomerReference($this->provider_customer_id)));
    }
}
