<?php

declare(strict_types=1);

namespace Andriichuk\LaravelBilling\Tests\Fixtures;

use Andriichuk\LaravelBilling\Concerns\Billable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string|null $email
 */
final class User extends Model
{
    use Billable;

    protected $guarded = [];
}
