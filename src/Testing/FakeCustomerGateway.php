<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Testing;

use Andriichuk\LaravelBilling\Contracts\ManagesCustomers;
use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\UpdateCustomerData;
use Andriichuk\LaravelBilling\Exceptions\BillingResourceNotFound;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;

final class FakeCustomerGateway implements ManagesCustomers
{
    public function __construct(private readonly FakeStore $store) {}

    public function createCustomer(CreateCustomerData $data): CustomerData
    {
        $this->store->record('createCustomer', $data);
        $id = 'cus_fake_'.(++$this->store->customerSequence);

        return $this->store->customers[$id] = new CustomerData(new CustomerReference($id), $data->name, $data->email, providerData: ['fake' => true], metadata: $data->metadata);
    }

    public function updateCustomer(CustomerReference $customer, UpdateCustomerData $data): CustomerData
    {
        $this->store->record('updateCustomer', $data);
        $existing = $this->retrieveCustomer($customer);

        return $this->store->customers[$customer->id] = new CustomerData($customer, $data->name ?? $existing->name, $data->email ?? $existing->email, $existing->trialEndsAt, $existing->rawProviderData(), array_replace($existing->metadata, $data->metadata));
    }

    public function retrieveCustomer(CustomerReference $customer): CustomerData
    {
        $this->store->record('retrieveCustomer', $customer);

        return $this->store->customers[$customer->id] ?? throw new BillingResourceNotFound("Fake customer [{$customer->id}] was not found.");
    }
}
