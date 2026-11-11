<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit trail of audited global-scope bypass invocations via withoutTenantScope() (W8-D51).
 */
class CoreTenantScopeBypass extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'core_tenant_scope_bypasses';

    protected $fillable = [
        'actor_user_id',
        'tenant_id',
        'ability',
        'model_class',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
        ];
    }
}

