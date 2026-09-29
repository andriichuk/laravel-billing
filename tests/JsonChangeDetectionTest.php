<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Tests;

use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Enums\TransactionStatus;
use Andriichuk\LaravelBilling\Events\SubscriptionUpdated;
use Andriichuk\LaravelBilling\Events\TransactionUpdated;
use Andriichuk\LaravelBilling\Models\Subscription;
use Andriichuk\LaravelBilling\Support\BillingSynchronizer;
use Andriichuk\LaravelBilling\Tests\Fixtures\User;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

final class JsonChangeDetectionTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private const array PROVIDER_DATA = [
        'subscriptionId' => 1,
        'status' => 'ACTIVE',
        'planId' => 2,
        'nested' => ['longKey' => 'value', 'id' => 10],
    ];

    /**
     * @var array<string, mixed>
     */
    private const array METADATA = [
        'externalReference' => 'reference',
        'source' => 'test',
    ];

    #[Test]
    public function customer_json_object_key_order_does_not_mark_a_resync_as_changed(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $data = new CustomerData(new CustomerReference('cus-1'), providerData: self::PROVIDER_DATA, metadata: self::METADATA);
        $synchronizer = app(BillingSynchronizer::class);
        $first = $synchronizer->customer($user, 'fake', $data);
        $id = $first->getKey();
        self::assertIsInt($id);

        $this->reorderStoredJson('billing_customers', $id);

        self::assertFalse($synchronizer->customerWouldChange($user, 'fake', $data));
        $second = $synchronizer->customer($user, 'fake', $data);
        self::assertFalse($second->wasChanged(), json_encode($second->getChanges(), JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function subscription_json_object_key_order_does_not_rewrite_or_replay(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $data = new SubscriptionData(new SubscriptionReference('sub-1'), 'default', SubscriptionStatus::Active, providerData: self::PROVIDER_DATA, metadata: self::METADATA);
        $synchronizer = app(BillingSynchronizer::class);
        $first = $synchronizer->subscription($user, 'fake', $data);
        $id = $first->getKey();
        self::assertIsInt($id);

        $this->reorderStoredJson('billing_subscriptions', $id);
        Event::fake([SubscriptionUpdated::class]);

        self::assertFalse($synchronizer->subscriptionWouldChange($user, 'fake', $data));
        $second = $synchronizer->subscription($user, 'fake', $data);

        self::assertFalse($second->wasChanged(), json_encode($second->getChanges(), JSON_THROW_ON_ERROR));
        Event::assertNotDispatched(SubscriptionUpdated::class);
    }

    #[Test]
    public function transaction_json_object_key_order_does_not_rewrite_or_replay(): void
    {
        $user = User::query()->create(['name' => 'Ada']);
        $data = new TransactionData(new TransactionReference('txn-1'), TransactionStatus::Succeeded, providerData: self::PROVIDER_DATA, metadata: self::METADATA);
        $synchronizer = app(BillingSynchronizer::class);
        $first = $synchronizer->transaction($user, 'fake', $data);
        $id = $first->getKey();
        self::assertIsInt($id);

        $this->reorderStoredJson('billing_transactions', $id);
        Event::fake([TransactionUpdated::class]);

        self::assertFalse($synchronizer->transactionWouldChange($user, 'fake', $data));
        $second = $synchronizer->transaction($user, 'fake', $data);

        self::assertFalse($second->wasChanged(), json_encode($second->getChanges(), JSON_THROW_ON_ERROR));
        Event::assertNotDispatched(TransactionUpdated::class);
    }

    #[Test]
    public function json_list_order_remains_significant(): void
    {
        $subscription = new Subscription;
        $subscription->setRawAttributes([
            'provider_data' => json_encode(['items' => ['first', 'second']], JSON_THROW_ON_ERROR),
        ], true);
        $subscription->provider_data = ['items' => ['second', 'first']];

        self::assertTrue($subscription->isDirty('provider_data'));
    }

    private function reorderStoredJson(string $table, int $id): void
    {
        DB::table($table)->where('id', $id)->update([
            'provider_data' => json_encode([
                'status' => 'ACTIVE',
                'planId' => 2,
                'subscriptionId' => 1,
                'nested' => ['id' => 10, 'longKey' => 'value'],
            ], JSON_THROW_ON_ERROR),
            'metadata' => json_encode([
                'source' => 'test',
                'externalReference' => 'reference',
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
