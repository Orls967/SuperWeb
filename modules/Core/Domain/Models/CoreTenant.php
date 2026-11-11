<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Isolated enterprise tenant / legal entity partition (W8-D51).
 *
 * @property string $id
 */
class CoreTenant extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'core_tenants';

    protected $fillable = [
        'id',
        'code',
        'name',
        'numeric_slot',
        'entity_uuid',
        'party_uuid',
        'is_sandbox',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'numeric_slot' => 'integer',
            'is_sandbox' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CoreTenantMembership::class, 'tenant_id');
    }

    public function apiClients(): HasMany
    {
        return $this->hasMany(CoreApiClient::class, 'tenant_id');
    }
}

