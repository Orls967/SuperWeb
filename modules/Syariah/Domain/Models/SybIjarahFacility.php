<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Syariah\Domain\Enums\IjarahStatus;

/**
 * Model Fasilitas Akad Sewa Ijarah & IMBT (D162-2).
 */
final class SybIjarahFacility extends Model
{
    protected $table = 'syb_ijarah_facilities';

    protected $fillable = [
        'contract_code',
        'customer_user_id',
        'asset_name',
        'asset_cost_idr',
        'tenor_months',
        'total_ujrah_idr',
        'residual_option_price_idr',
        'status',
        'dps_reviewer_user_id',
        'dps_reviewed_at',
        'dps_opinion_note',
        'activated_at',
        'option_exercised_at',
        'ownership_transferred_at',
        'total_ujrah_paid_idr',
        'accumulated_depreciation_idr',
        'carrying_book_value_idr',
        'total_tazir_idr',
        'idempotency_key',
    ];

    protected $casts = [
        'customer_user_id' => 'integer',
        'asset_cost_idr' => 'integer',
        'tenor_months' => 'integer',
        'total_ujrah_idr' => 'integer',
        'residual_option_price_idr' => 'integer',
        'status' => IjarahStatus::class,
        'dps_reviewer_user_id' => 'integer',
        'dps_reviewed_at' => 'datetime',
        'activated_at' => 'datetime',
        'option_exercised_at' => 'datetime',
        'ownership_transferred_at' => 'datetime',
        'total_ujrah_paid_idr' => 'integer',
        'accumulated_depreciation_idr' => 'integer',
        'carrying_book_value_idr' => 'integer',
        'total_tazir_idr' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function dpsReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dps_reviewer_user_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SybIjarahFacilitySchedule::class, 'facility_id');
    }
}

