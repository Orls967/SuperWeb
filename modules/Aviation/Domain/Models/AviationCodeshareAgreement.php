<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationCodeshareAgreement extends Model
{
    protected $table = 'avi_codeshare_agreements';

    protected $fillable = [
        'operating_airline_id',
        'marketing_airline_id',
        'agreement_code',
        'agreement_type',
        'revenue_split_bps',
        'proration_rule',
        'status',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'revenue_split_bps' => 'integer',
    ];

    public function operatingAirline(): BelongsTo
    {
        return $this->belongsTo(AviationAirline::class, 'operating_airline_id');
    }

    public function marketingAirline(): BelongsTo
    {
        return $this->belongsTo(AviationAirline::class, 'marketing_airline_id');
    }

    public function codeshareFlights(): HasMany
    {
        return $this->hasMany(AviationCodeshareFlight::class, 'codeshare_agreement_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
