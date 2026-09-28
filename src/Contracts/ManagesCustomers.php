<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
use Andriichuk\LaravelBilling\Data\CreateCustomerData;
use Andriichuk\LaravelBilling\Data\CustomerData;
use Andriichuk\LaravelBilling\Data\UpdateCustomerData;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
interface ManagesCustomers { public function createCustomer(CreateCustomerData $data): CustomerData; public function updateCustomer(CustomerReference $customer, UpdateCustomerData $data): CustomerData; public function retrieveCustomer(CustomerReference $customer): CustomerData; }
