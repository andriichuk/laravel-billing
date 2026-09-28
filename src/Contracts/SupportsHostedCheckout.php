<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Contracts;

interface SupportsHostedCheckout
{
    /**
     * @param  array<string,mixed>  $options
     */
    public function hostedCheckoutUrl(array $options): string;
}
