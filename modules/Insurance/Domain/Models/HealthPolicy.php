<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthPolicy extends Model
{
    protected $table = 'ins_lh_health_policies';

    /** @var list<string> */
    protected $fillable = [
        'policy_number',
        'member_party_id',
        'plan_tier',
        'annual_limit_idr',
        'annual_utilized_idr',
        'room_daily_limit_idr',
        'deductible_idr',
        'status',
        'effective_date',
        'expiry_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'annual_limit_idr' => 'integer',
            'annual_utilized_idr' => 'integer',
            'room_daily_limit_idr' => 'integer',
            'deductible_idr' => 'integer',
            'effective_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /** @return HasMany<GuaranteeLetter, $this> */
    public function guaranteeLetters(): HasMany
    {
        return $this->hasMany(GuaranteeLetter::class, 'health_policy_id');
    }

    /** @return HasMany<HealthClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(HealthClaim::class, 'health_policy_id');
    }
}
