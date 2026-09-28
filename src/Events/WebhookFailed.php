<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Events;
use Andriichuk\LaravelBilling\Models\WebhookEvent;
use Throwable;
final readonly class WebhookFailed { public function __construct(public WebhookEvent $webhook, public Throwable $exception) {} }
