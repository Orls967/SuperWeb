<?php

declare(strict_types=1);

namespace Modules\Procurement\Infrastructure\Retention;

use Modules\Core\Contracts\RetentionArchiveProvider;

class ProcurementPoVersionRetentionProvider implements RetentionArchiveProvider
{
    public function moduleName(): string
    {
        return 'Procurement';
    }

    public function sourceTable(): string
    {
        return 'prc_po_versions';
    }

    public function archiveTable(): string
    {
        return 'prc_po_versions_archive';
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
        return ['id', 'po_id', 'version', 'status'];
    }
}

