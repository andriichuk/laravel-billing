<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Concerns;

use Override;

trait ComparesJsonAttributes
{
    /**
     * @return list<string>
     */
    protected function orderInsensitiveJsonAttributes(): array
    {
        return ['provider_data', 'metadata'];
    }

    #[Override]
    public function originalIsEquivalent($key): bool
    {
        if (! in_array($key, $this->orderInsensitiveJsonAttributes(), true)) {
            return parent::originalIsEquivalent($key);
        }

        if (! array_key_exists($key, $this->original)) {
            return false;
        }

        return $this->canonicalJsonValue($this->decodeJsonValue($this->attributes[$key] ?? null))
            === $this->canonicalJsonValue($this->decodeJsonValue($this->original[$key]));
    }

    private function decodeJsonValue(mixed $value): mixed
    {
        return is_string($value) ? $this->fromJson($value) : $value;
    }

    private function canonicalJsonValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map($this->canonicalJsonValue(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
