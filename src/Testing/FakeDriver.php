<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Testing;

use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Andriichuk\LaravelBilling\Contracts\ManagesCustomers;
use Andriichuk\LaravelBilling\Contracts\ManagesSubscriptions;
use Andriichuk\LaravelBilling\Contracts\ManagesTransactions;
use Andriichuk\LaravelBilling\Contracts\ProcessesWebhooks;
use Andriichuk\LaravelBilling\Contracts\ReconcilesResources;
use Andriichuk\LaravelBilling\Contracts\SupportsSubscriptionTrials;
use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\CreateSubscriptionData;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\ParsedWebhook;
use Andriichuk\LaravelBilling\Data\ReconciliationRequest;
use Andriichuk\LaravelBilling\Data\ReconciliationResult;
use Andriichuk\LaravelBilling\Data\SubscriptionData;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Data\UpdateCustomerData;
use Andriichuk\LaravelBilling\Data\UpdateSubscriptionData;
use Andriichuk\LaravelBilling\Data\WebhookRequest;
use Andriichuk\LaravelBilling\Enums\CancellationMode;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\SubscriptionReference;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
use Throwable;

final class FakeDriver implements BillingDriver, ManagesCustomers, ManagesSubscriptions, ManagesTransactions, ProcessesWebhooks, ReconcilesResources, SupportsSubscriptionTrials
{
    private FakeCustomerGateway $customers;

    private FakeSubscriptionGateway $subscriptions;

    private FakeTransactionGateway $transactions;

    private FakeWebhookGateway $webhooks;

    /** @param list<Capability>|null $capabilities */
    public function __construct(
        private readonly string $driverName = 'fake',
        ?array $capabilities = null,
        string $webhookSecret = 'fake-secret',
        ?FakeStore $store = null
    ) {
        $this->store = $store ?? new FakeStore;
        $this->driverCapabilities = $capabilities ?? [Capability::Customers, Capability::Subscriptions, Capability::Transactions, Capability::Webhooks, Capability::Reconciliation, Capability::SubscriptionTrials];
        $this->customers = new FakeCustomerGateway($this->store);
        $this->subscriptions = new FakeSubscriptionGateway($this->store);
        $this->transactions = new FakeTransactionGateway($this->store);
        $this->webhooks = new FakeWebhookGateway($this->store, $webhookSecret);
    }

    private FakeStore $store;

    /** @var list<Capability> */
    private array $driverCapabilities;

    public function name(): string
    {
        return $this->driverName;
    }

    public function capabilities(): array
    {
        return $this->driverCapabilities;
    }

    public function supports(Capability $capability): bool
    {
        return in_array($capability, $this->driverCapabilities, true);
    }

    public function store(): FakeStore
    {
        return $this->store;
    }

    public function failNext(string $operation, Throwable $failure): self
    {
        $this->store->failures[$operation] = $failure;

        return $this;
    }

    /** @return list<array{operation:string,data:mixed}> */
    public function recordedRequests(): array
    {
        return $this->store->requests;
    }

    public function createCustomer(CreateCustomerData $data): CustomerData
    {
        return $this->customers->createCustomer($data);
    }

    public function updateCustomer(CustomerReference $customer, UpdateCustomerData $data): CustomerData
    {
        return $this->customers->updateCustomer($customer, $data);
    }

    public function retrieveCustomer(CustomerReference $customer): CustomerData
    {
        return $this->customers->retrieveCustomer($customer);
    }

    public function createSubscription(CreateSubscriptionData $data): SubscriptionData
    {
        return $this->subscriptions->createSubscription($data);
    }

    public function updateSubscription(SubscriptionReference $subscription, UpdateSubscriptionData $data): SubscriptionData
    {
        return $this->subscriptions->updateSubscription($subscription, $data);
    }

    public function cancelSubscription(SubscriptionReference $subscription, CancellationMode $mode): SubscriptionData
    {
        return $this->subscriptions->cancelSubscription($subscription, $mode);
    }

    public function retrieveSubscription(SubscriptionReference $subscription): SubscriptionData
    {
        return $this->subscriptions->retrieveSubscription($subscription);
    }

    public function retrieveTransaction(TransactionReference $transaction): TransactionData
    {
        return $this->transactions->retrieveTransaction($transaction);
    }

    public function verifyWebhook(WebhookRequest $request): void
    {
        $this->webhooks->verifyWebhook($request);
    }

    public function parseWebhook(WebhookRequest $request): ParsedWebhook
    {
        return $this->webhooks->parseWebhook($request);
    }

    public function reconcile(ReconciliationRequest $request): iterable
    {
        $this->store->record('reconcile', $request);

        foreach ($this->store->reconciliationResults as $result) {
            yield $result;
        }
    }

    public function addReconciliationResult(ReconciliationResult $result): self
    {
        $this->store->reconciliationResults[] = $result;

        return $this;
    }
}
