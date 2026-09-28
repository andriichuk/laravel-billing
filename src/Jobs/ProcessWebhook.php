<?php
declare(strict_types=1);
namespace Andriichuk\LaravelBilling\Jobs;
use Andriichuk\LaravelBilling\Models\WebhookEvent;
use Andriichuk\LaravelBilling\Webhooks\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
final class ProcessWebhook implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public int $uniqueFor = 3600;
    public function __construct(public readonly int|string $webhookId) {}
    public function uniqueId(): string { $event = WebhookEvent::query()->find($this->webhookId); return $event === null ? (string) $this->webhookId : $event->driver.':'.$event->event_key; }
    public function handle(WebhookProcessor $processor): void { $event = WebhookEvent::query()->findOrFail($this->webhookId); $processor->process($event); }
}
