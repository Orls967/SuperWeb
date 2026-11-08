<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationCodeshareFlight extends Model
{
    protected $table = 'avi_codeshare_flights';

    protected $fillable = [
        'codeshare_agreement_id',
        'operating_flight_id',
        'marketing_flight_number',
        'allotted_seats',
        'status',
    ];

    protected $casts = [
        'allotted_seats' => 'integer',
    ];

    public function codeshareAgreement(): BelongsTo
    {
        return $this->belongsTo(AviationCodeshareAgreement::class, 'codeshare_agreement_id');
    }

    public function operatingFlight(): BelongsTo
    {
        return $this->belongsTo(AviationFlight::class, 'operating_flight_id');
    }
}
