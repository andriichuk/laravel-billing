<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Contracts;
use Andriichuk\LaravelBilling\ValueObjects\CustomerReference;
use Andriichuk\LaravelBilling\ValueObjects\PaymentMethodReference;
interface SupportsPaymentMethodUpdates { public function updatePaymentMethod(CustomerReference $customer, PaymentMethodReference $paymentMethod): void; }
