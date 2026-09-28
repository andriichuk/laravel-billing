<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling;

use Andriichuk\LaravelBilling\Contracts\BillingDriver;
use Andriichuk\LaravelBilling\Enums\Capability;
use Andriichuk\LaravelBilling\Exceptions\UnsupportedCapability;
use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class BillingManager
{
    /** @var array<string, Closure(Container, array<string,mixed>): BillingDriver> */
    private array $extensions = [];

    /** @var array<string, BillingDriver> */
    private array $drivers = [];

    public function __construct(private readonly Container $container) {}

    /** @param Closure(Container, array<string,mixed>): BillingDriver $resolver */
    public function extend(string $name, Closure $resolver): self
    {
        $this->extensions[$name] = $resolver;
        unset($this->drivers[$name]);

        return $this;
    }

    public function driver(?string $name = null): BillingDriver
    {
        $name ??= $this->defaultDriver();

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $resolver = $this->extensions[$name] ?? null;

        if (! $resolver instanceof Closure) {
            throw new InvalidArgumentException("Billing driver [{$name}] has not been registered.");
        }

        $config = $this->driverConfig($name);
        $driver = $resolver($this->container, $config);

        if ($driver->name() !== $name) {
            throw new InvalidArgumentException("Resolved billing driver must identify itself as [{$name}].");
        }

        return $this->drivers[$name] = $driver;
    }

    public function require(Capability $capability, ?string $driver = null): BillingDriver
    {
        $instance = $this->driver($driver);

        if (! $instance->supports($capability)) {
            throw UnsupportedCapability::for($instance, $capability);
        }

        return $instance;
    }

    public function forgetDrivers(): void
    {
        $this->drivers = [];
    }

    /** @return list<string> */
    public function registeredDrivers(): array
    {
        return array_keys($this->extensions);
    }

    /** @return array<string, mixed> */
    private function driverConfig(string $name): array
    {
        $configured = $this->container->make('config')->get("billing.drivers.{$name}", []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException("Billing driver configuration [{$name}] must be an array.");
        }

        $config = [];

        foreach ($configured as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException("Billing driver configuration [{$name}] must use string keys.");
            }

            $config[$key] = $value;
        }

        return $config;
    }

    public function defaultDriver(): string
    {
        $name = $this->container->make('config')->get('billing.default');

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('A default billing driver must be configured.');
        }

        return $name;
    }
}
