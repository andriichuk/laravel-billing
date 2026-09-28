<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Models;

use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Contracts\ManagesTransactions;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Enums\TransactionStatus;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $driver
 * @property string $provider_transaction_id
 * @property string|null $provider_subscription_id
 * @property TransactionStatus $status
 * @property string|null $amount
 * @property string|null $currency
 */
class Transaction extends Model
{
    protected $table = 'billing_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => TransactionStatus::class, 'billed_at' => 'immutable_datetime', 'provider_data' => 'array', 'metadata' => 'array'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function syncFromProvider(): self
    {
        $driver = app(BillingManager::class)->require(Capability::Transactions, $this->driver);

        if (! $driver instanceof ManagesTransactions) {
            throw UnsupportedCapability::for($driver, Capability::Transactions);
        }

        $billable = $this->billable()->first();

        if (! $billable instanceof Model) {
            throw new \LogicException('Billing transaction has no billable model.');
        }

        return app(BillingSynchronizer::class)->transaction($billable, $this->driver, $driver->retrieveTransaction(new TransactionReference($this->provider_transaction_id)));
    }
}
