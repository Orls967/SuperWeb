<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use App\Security\BlindIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Shared\Domain\Traits\HasUuid;

class Tenant extends Model
{
    use HasUuid;

    protected $table = 'mall_tenants';

    protected $fillable = [
        'uuid',
        'user_id',
        'company_name',
        'brand_name',
        'pic_name',
        'pic_phone',
        'pic_email',
        'npwp',
        'npwp_bidx',
        'external_ref',
        'category',
        'is_active',
    ];

    protected $casts = [
        'npwp' => 'encrypted',
        'category' => TenantCategory::class,
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $tenant) {
            if ($tenant->isDirty('npwp')) {
                $tenant->npwp_bidx = BlindIndex::forNpwp($tenant->npwp);
            }
        });
    }

    /**
     * Find tenant by NPWP via deterministic blind index.
     */
    public static function findByNpwp(string $npwp): ?self
    {
        $bidx = BlindIndex::forNpwp($npwp);

        return self::where('npwp_bidx', $bidx)->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    public function activeLeases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id')->where('status', LeaseStatus::ACTIVE);
    }

    public function salesReports(): HasMany
    {
        return $this->hasMany(TenantSalesReport::class, 'tenant_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'tenant_id');
    }
}
