<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * B2B API client account with tiered B1 integer token-bucket quota (W8-D47 / 55.5).
 */
class CoreApiClient extends Model
{
    public const TIER_DEFAULTS = [
        'SILVER' => ['capacity' => 60, 'refill_per_sec' => 1, 'daily_quota' => 10000],
        'GOLD' => ['capacity' => 300, 'refill_per_sec' => 5, 'daily_quota' => 100000],
        'PLATINUM' => ['capacity' => 1200, 'refill_per_sec' => 20, 'daily_quota' => 1000000],
    ];

    protected $table = 'core_api_clients';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'code',
        'name',
        'legal_entity_name',
        'tier',
        'capacity',
        'refill_per_sec',
        'daily_quota',
        'bucket_milli_tokens',
        'bucket_last_ms',
        'is_active',
        'is_sandbox',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'capacity' => 'integer',
            'refill_per_sec' => 'integer',
            'daily_quota' => 'integer',
            'bucket_milli_tokens' => 'integer',
            'bucket_last_ms' => 'integer',
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
        ];
    }

    public function getClientCodeAttribute(): string
    {
        return (string) ($this->attributes['code'] ?? '');
    }

    public function setClientCodeAttribute(string $value): void
    {
        $this->attributes['code'] = $value;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CoreTenant::class, 'tenant_id');
    }

    public function keys(): HasMany
    {
        return $this->hasMany(CoreApiClientKey::class, 'api_client_id');
    }

    public function usageRollups(): HasMany
    {
        return $this->hasMany(CoreApiUsageRollup::class, 'api_client_id');
    }
}
