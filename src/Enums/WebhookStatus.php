<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Enums;
enum WebhookStatus: string { case Received = 'received'; case Queued = 'queued'; case Processing = 'processing'; case Processed = 'processed'; case Ignored = 'ignored'; case Failed = 'failed'; }
