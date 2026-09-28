<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Tests;
use Andriichuk\LaravelBilling\Enums\SubscriptionStatus;
use Andriichuk\LaravelBilling\Models\Subscription;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
final class SubscriptionStateTest extends TestCase
{
    #[Test] public function normalized_state_helpers_have_explicit_semantics(): void { $subscription = new Subscription(['status' => SubscriptionStatus::Trialing, 'trial_ends_at' => CarbonImmutable::now()->addDay(), 'auto_renew' => true]); self::assertTrue($subscription->trialing()); self::assertTrue($subscription->onTrial()); self::assertTrue($subscription->valid()); $subscription->status = SubscriptionStatus::Canceled; $subscription->ends_at = CarbonImmutable::now()->addDay(); self::assertTrue($subscription->onGracePeriod()); self::assertTrue($subscription->valid()); self::assertFalse($subscription->recurring()); }
}
