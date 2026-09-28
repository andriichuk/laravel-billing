<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Enums;
enum TransactionStatus: string { case Succeeded = 'succeeded'; case Pending = 'pending'; case Failed = 'failed'; case Refunded = 'refunded'; case Disputed = 'disputed'; case Unknown = 'unknown'; }
