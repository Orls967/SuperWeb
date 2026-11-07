<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HotelApoaReport extends Model
{
    protected $table = 'htl_apoa_reports';

    protected $fillable = [
        'reservation_id',
        'property_id',
        'guest_user_id',
        'nationality_country_code',
        'passport_blind_index',
        'passport_encrypted',
        'visa_type',
        'bali_pwa_levy_verified',
        'apoa_submission_batch',
        'acknowledgement_reference',
        'deadline_at',
        'submitted_at',
        'acknowledged_at',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'bali_pwa_levy_verified' => 'boolean',
        'deadline_at' => 'datetime',
        'submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'passport_encrypted' => 'encrypted',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(HotelReservation::class, 'reservation_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }
}
