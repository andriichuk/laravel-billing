<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Testing;
use Andriichuk\LaravelBilling\Contracts\ManagesTransactions;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\Exceptions\BillingResourceNotFound;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
final class FakeTransactionGateway implements ManagesTransactions
{
    public function __construct(private readonly FakeStore $store) {}
    public function retrieveTransaction(TransactionReference $transaction): TransactionData { $this->store->record('retrieveTransaction', $transaction); return $this->store->transactions[$transaction->id] ?? throw new BillingResourceNotFound("Fake transaction [{$transaction->id}] was not found."); }
}
