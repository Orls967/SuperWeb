<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Retention;

use Modules\Core\Contracts\RetentionArchiveProvider;

class CoreAuditLogRetentionProvider implements RetentionArchiveProvider
{
    public function moduleName(): string
    {
        return 'Core';
    }

    public function sourceTable(): string
    {
        return 'core_audit_logs';
    }

    public function archiveTable(): string
    {
        return 'core_audit_logs_archive';
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
        return ['id', 'action', 'auditable_type', 'auditable_id'];
    }
}
