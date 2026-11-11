<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps an authenticated User to allowed tenant/legal-entity/party scopes (W8-D51).
 */
class CoreTenantMembership extends Model
{
    protected $table = 'core_tenant_memberships';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'allowed_entities',
        'allowed_parties',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'allowed_entities' => 'array',
            'allowed_parties' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CoreTenant::class, 'tenant_id');
    }
}
