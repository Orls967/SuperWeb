<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Illuminate\Events\Dispatcher;
use Modules\Platform\Domain\Events\SandboxCreatedEvent;
use Modules\Platform\Domain\Events\SandboxDestroyedEvent;
use Modules\Platform\Domain\Events\SandboxExpiredEvent;
use Modules\Platform\Domain\Events\SandboxProvisionedEvent;

/**
 * Subscriber pencatatan jejak audit siklus hidup sandbox developer.
 */
class SandboxLifecycleAuditSubscriber
{
    public function __construct(
        protected SandboxLogAggregator $aggregator = new SandboxLogAggregator()
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            SandboxCreatedEvent::class => 'onCreated',
            SandboxProvisionedEvent::class => 'onProvisioned',
            SandboxExpiredEvent::class => 'onExpired',
            SandboxDestroyedEvent::class => 'onDestroyed',
        ];
    }

    public function onCreated(SandboxCreatedEvent $event): void
    {
        $this->aggregator->appendLog($event->sandbox, 'INFO', "Sandbox environment created: {$event->sandbox->name}");
    }

    public function onProvisioned(SandboxProvisionedEvent $event): void
    {
        $this->aggregator->appendLog($event->sandbox, 'INFO', "Sandbox schema provisioned and ready: {$event->sandbox->name}");
    }

    public function onExpired(SandboxExpiredEvent $event): void
    {
        $this->aggregator->appendLog($event->sandbox, 'WARNING', "Sandbox TTL expired: {$event->sandbox->name}");
    }

    public function onDestroyed(SandboxDestroyedEvent $event): void
    {
        $this->aggregator->appendLog($event->sandbox, 'NOTICE', "Sandbox decommissioned and destroyed: {$event->sandbox->name}");
    }
}
