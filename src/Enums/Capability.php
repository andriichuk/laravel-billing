<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Enums;

enum Capability: string
{
    case Customers = 'customers';
    case Subscriptions = 'subscriptions';
    case Transactions = 'transactions';
    case Webhooks = 'webhooks';
    case Reconciliation = 'reconciliation';
    case SubscriptionTrials = 'subscription_trials';
    case PlanChanges = 'plan_changes';
    case QuantityChanges = 'quantity_changes';
    case Proration = 'proration';
    case SubscriptionPausing = 'subscription_pausing';
    case HostedCheckout = 'hosted_checkout';
    case Refunds = 'refunds';
    case Invoices = 'invoices';
    case UsageBilling = 'usage_billing';
    case PaymentMethodUpdates = 'payment_method_updates';
}
