<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Events;
use Andriichuk\LaravelBilling\Data\Events\NormalizedEvent;
use Andriichuk\LaravelBilling\Models\Subscription;
final readonly class SubscriptionUpdated { public function __construct(public Subscription $subscription, public NormalizedEvent $source) {} }
