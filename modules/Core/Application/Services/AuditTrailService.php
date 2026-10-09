<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Domain\Models\AuditLog;

class AuditTrailService implements AuditTrailInterface
{
    public function record(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
        ?string $correlationId = null,
        ?string $impactType = null,
        ?User $user = null
    ): AuditLog {
        return AuditLog::record(
            action: $action,
            auditable: $auditable,
            context: $context,
            oldValues: $oldValues,
            newValues: $newValues,
            user: $user,
            correlationId: $correlationId,
            impactType: $impactType
        );
    }
}
