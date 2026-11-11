<?php

declare(strict_types=1);

namespace Modules\Logistics\Infrastructure\Retention;

use Modules\Core\Contracts\RetentionArchiveProvider;

class LogisticsTrackingEventRetentionProvider implements RetentionArchiveProvider
{
    public function moduleName(): string
    {
        return 'Logistics';
    }

    public function sourceTable(): string
    {
        return 'lgx_tracking_events';
    }

    public function archiveTable(): string
    {
        return 'lgx_tracking_events_archive';
    }

    public function timestampColumn(): string
    {
        return 'created_at';
    }

    public function primaryKeyColumn(): string
    {
        return 'id';
    }

    public function hashColumns(): array
    {
        return ['id', 'shipment_id', 'event_type', 'occurred_at'];
    }
}
