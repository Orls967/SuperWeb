<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Shared\Domain\Traits\HasUuid;

class ParkingMember extends Model
{
    use HasUuid;

    protected $table = 'mall_parking_members';

    protected $fillable = [
        'uuid',
        'property_id',
        'user_id',
        'tenant_id',
        'vehicle_id',
        'member_number',
        'plate_number',
        'vehicle_type',
        'monthly_price',
        'auto_renew',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'vehicle_type' => VehicleType::class,
        'monthly_price' => 'integer',
        'auto_renew' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => MemberStatus::class,
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class, 'member_id');
    }

    /**
     * Cek keabsahan status keanggotaan member parkir.
     */
    public function isValid(): bool
    {
        if ($this->status !== MemberStatus::ACTIVE) {
            return false;
        }

        return Carbon::now()->startOfDay()->lessThanOrEqualTo(Carbon::parse($this->end_date)->startOfDay());
    }

    /**
     * Sisa hari keanggotaan; negatif berarti sudah lewat masa aktif.
     */
    public function daysRemaining(): int
    {
        return (int) Carbon::now()->startOfDay()->diffInDays(
            Carbon::parse($this->end_date)->startOfDay(),
            false
        );
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp '.number_format($this->monthly_price, 0, ',', '.');
    }
}
