<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
use Andriichuk\LaravelBilling\Data\TransactionData;
use Andriichuk\LaravelBilling\ValueObjects\TransactionReference;
interface ManagesTransactions { public function retrieveTransaction(TransactionReference $transaction): TransactionData; }
