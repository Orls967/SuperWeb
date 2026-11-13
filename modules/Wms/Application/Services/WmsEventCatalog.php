<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Wms\Domain\Events\WarehouseStockAdjustedEvent;
use Modules\Wms\Domain\Events\WarehouseTaskConfirmedEvent;
use Modules\Wms\Domain\Events\WarehouseTransferReceivedEvent;

/**
 * Katalog event modul WMS & penerbit outbox deterministik.
 */
final class WmsEventCatalog
{
    /** @var list<string> */
    public const array TOPICS = [
        WarehouseTaskConfirmedEvent::TOPIC,
        WarehouseStockAdjustedEvent::TOPIC,
        WarehouseTransferReceivedEvent::TOPIC,
    ];

    public function __construct(
        private readonly ?OutboxBusInterface $outbox = null,
    ) {}

    public function publishTaskConfirmed(WarehouseTaskConfirmedEvent $event): void
    {
        $this->outbox?->publish(WarehouseTaskConfirmedEvent::TOPIC, $event->toPayload());
    }

    public function publishStockAdjusted(WarehouseStockAdjustedEvent $event): void
    {
        $this->outbox?->publish(WarehouseStockAdjustedEvent::TOPIC, $event->toPayload());
    }

    public function publishTransferReceived(WarehouseTransferReceivedEvent $event): void
    {
        $this->outbox?->publish(WarehouseTransferReceivedEvent::TOPIC, $event->toPayload());
    }
}
