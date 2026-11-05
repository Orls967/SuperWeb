<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AgriInsurancePolicy extends Model
{
    protected $table = 'ins_agri_policies';

    protected $fillable = [
        'policy_number',
        'farmer_party_id',
        'commodity',
        'farm_area_ha',
        'sum_insured_idr',
        'premium_annual_idr',
        'index_type',
        'trigger_threshold_val',
        'status',
        'effective_date',
        'expiry_date',
    ];

    protected $casts = [
        'farm_area_ha' => 'integer',
        'sum_insured_idr' => 'integer',
        'premium_annual_idr' => 'integer',
        'trigger_threshold_val' => 'integer',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(AgriInsuranceClaim::class, 'policy_id');
    }
}
