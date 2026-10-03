<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\AuditLog;

interface AuditTrailInterface
{
    /**
     * Record an audit log for an impactful action.
     */
    public function record(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
        ?string $correlationId = null,
        ?string $impactType = null,
        ?User $user = null
    ): AuditLog;
}
