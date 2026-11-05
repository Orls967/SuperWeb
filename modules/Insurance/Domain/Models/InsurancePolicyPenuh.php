<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InsurancePolicyPenuh extends Model
{
    protected $table = 'ins_full_policies';

    protected $fillable = [
        'policy_number',
        'product_id',
        'customer_party_ref',
        'sum_insured_idr',
        'gross_premium_idr',
        'ceded_premium_idr',
        'retained_premium_idr',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'customer_party_ref' => 'integer',
        'sum_insured_idr' => 'integer',
        'gross_premium_idr' => 'integer',
        'ceded_premium_idr' => 'integer',
        'retained_premium_idr' => 'integer',
        'starts_at' => 'date',
        'expires_at' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InsuranceProductPenuh::class, 'product_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InsurancePremiumSchedule::class, 'policy_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaimPenuh::class, 'policy_id');
    }
}
