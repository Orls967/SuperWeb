<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Syariah\Domain\Enums\SukukStatus;

/**
 * Model Fasilitas Penerbitan Sukuk Ijarah (D162-1).
 */
final class SybSukukFacility extends Model
{
    protected $table = 'syb_sukuk_facilities';

    protected $fillable = [
        'sukuk_code',
        'facility_name',
        'asset_code',
        'rwa_asset_id',
        'face_value_idr',
        'total_units',
        'maturity_date',
        'annual_coupon_bps',
        'status',
        'spv_party_name',
        'dps_reviewer_user_id',
        'dps_reviewed_at',
        'dps_opinion_note',
        'issued_at',
        'redeemed_at',
        'total_coupon_distributed_idr',
        'idempotency_key',
    ];

    protected $casts = [
        'rwa_asset_id' => 'integer',
        'face_value_idr' => 'integer',
        'total_units' => 'integer',
        'maturity_date' => 'date',
        'annual_coupon_bps' => 'integer',
        'status' => SukukStatus::class,
        'dps_reviewer_user_id' => 'integer',
        'dps_reviewed_at' => 'datetime',
        'issued_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'total_coupon_distributed_idr' => 'integer',
    ];

    public function dpsReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dps_reviewer_user_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(SybSukukDistribution::class, 'sukuk_facility_id');
    }
}
