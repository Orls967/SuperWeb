<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetCertificate extends Model
{
    protected $table = 'prt_fleet_certificates';

    protected $fillable = [
        'vessel_id',
        'certificate_type',
        'certificate_number',
        'issuing_authority',
        'issue_date',
        'expiry_date',
        'annual_endorsement_due',
        'status',
        'endorsement_history',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'annual_endorsement_due' => 'date',
        'endorsement_history' => 'array',
    ];

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(FleetVessel::class, 'vessel_id');
    }

    public function isExpired(CarbonImmutable $at): bool
    {
        return CarbonImmutable::parse($this->expiry_date)->isBefore($at);
    }
}
