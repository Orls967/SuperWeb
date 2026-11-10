<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditFacility extends Model
{
    use HasUuids;

    protected $table = 'trs_credit_facilities';

    protected $fillable = [
        'facility_code',
        'entity_code',
        'entity_id',
        'bank_name',
        'credit_limit_idr',
        'drawn_amount_idr',
        'interest_rate_permil',
        'max_der_permil',
        'covenant_frequency',
        'interest_rate_percent',
        'max_debt_equity_ratio',
        'status',
    ];

    protected $casts = [
        'credit_limit_idr' => 'integer',
        'drawn_amount_idr' => 'integer',
        'interest_rate_permil' => 'integer',
        'max_der_permil' => 'integer',
        'interest_rate_percent' => 'float',
        'max_debt_equity_ratio' => 'float',
    ];

    public function drawdowns(): HasMany
    {
        return $this->hasMany(CreditFacilityDrawdown::class, 'facility_id');
    }

    public function availableLimitIdr(): int
    {
        return max(0, (int) $this->credit_limit_idr - (int) $this->drawn_amount_idr);
    }

    public function getEntityIdAttribute(): string
    {
        return (string) ($this->attributes['entity_code'] ?? $this->attributes['entity_id'] ?? 'HOLDING');
    }

    public function setEntityIdAttribute($value): void
    {
        $this->attributes['entity_code'] = $value;
    }
}
