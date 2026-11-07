<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningConcessionPermit extends Model
{
    use HasUuids;

    protected $table = 'min_concession_permits';

    protected $fillable = [
        'permit_number',
        'site_id',
        'permit_type',
        'concession_area_hectares',
        'issued_date',
        'expiry_date',
        'status',
        'renewal_application_ref',
    ];

    protected $casts = [
        'concession_area_hectares' => 'integer',
        'issued_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        $now = $now ?? CarbonImmutable::now();

        return $now->greaterThan($this->expiry_date);
    }

    public function getPermitCodeAttribute(): ?string
    {
        return $this->permit_number;
    }

    public function getValidUntilAttribute(): ?string
    {
        return $this->expiry_date?->toDateString();
    }

    public function getIssuingAuthorityAttribute(): string
    {
        return 'Ditjen Minerba ESDM';
    }
}
