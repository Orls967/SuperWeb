<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Shared\Domain\Traits\HasUuid;

class Unit extends Model
{
    use HasUuid;

    protected $table = 'mall_units';

    protected $fillable = [
        'uuid',
        'property_id',
        'zone_id',
        'unit_number',
        'floor',
        'area_sqm',
        'base_rent_rate_per_sqm',
        'service_charge_per_sqm',
        'status',
        'coordinates',
    ];

    protected $casts = [
        'area_sqm' => 'float',
        'base_rent_rate_per_sqm' => 'integer',
        'service_charge_per_sqm' => 'integer',
        'status' => UnitStatus::class,
        'coordinates' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'unit_id');
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class, 'unit_id')->where('status', LeaseStatus::ACTIVE);
    }

    public function isAvailableBetween(string|Carbon $startDate, string|Carbon $endDate, ?int $excludeLeaseId = null): bool
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        $overlapping = $this->leases()
            ->when($excludeLeaseId, fn ($q) => $q->where('id', '!=', $excludeLeaseId))
            ->whereIn('status', [LeaseStatus::DRAFT, LeaseStatus::ACTIVE])
            ->where(function ($query) use ($start, $end) {
                // Overlap: existing_start <= new_end AND existing_end >= new_start
                $query->whereDate('start_date', '<=', $end)
                    ->whereDate('end_date', '>=', $start);
            })
            ->exists();

        return ! $overlapping;
    }

    public function estimatedBaseRent(): int
    {
        return (int) round($this->area_sqm * $this->base_rent_rate_per_sqm);
    }

    public function estimatedServiceCharge(): int
    {
        return (int) round($this->area_sqm * $this->service_charge_per_sqm);
    }
}
