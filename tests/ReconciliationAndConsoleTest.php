<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Tests;
use Andriichuk\LaravelBilling\BillingManager;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Testing\FakeDriver;
use Andriichuk\LaravelBilling\Tests\Fixtures\User;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use PHPUnit\Framework\Attributes\Test;
final class ReconciliationAndConsoleTest extends TestCase
{
    #[Test] public function reconciliation_updates_the_local_projection_and_supports_dry_runs(): void
    {
        $user = User::query()->create(['name' => 'Ada']); $key = $user->getKey(); self::assertIsInt($key);
        $resolved = app(BillingManager::class)->driver(); self::assertInstanceOf(FakeDriver::class, $resolved);
        $resolved->addReconciliationResult(new ReconciliationResult('subscription', new SubscriptionData(new SubscriptionReference('remote-1'), 'default', SubscriptionStatus::Active, priceId: 'price-1'), $user->getMorphClass(), (string) $key));
        $this->artisanCommand('billing:reconcile', ['--dry-run' => true])->assertSuccessful(); self::assertDatabaseCount('billing_subscriptions', 0);
        $this->artisanCommand('billing:reconcile')->assertSuccessful(); self::assertDatabaseHas('billing_subscriptions', ['provider_subscription_id' => 'remote-1']);
    }
    #[Test] public function routes_and_safe_console_defaults_are_registered(): void
    {
        self::assertNotNull(app('router')->getRoutes()->getByName('billing.webhooks'));
        $this->artisanCommand('billing:webhooks:prune', ['--dry-run' => true])->assertSuccessful();
        $this->artisanCommand('billing:webhooks:retry', ['--dry-run' => true])->assertSuccessful();
    }
}
