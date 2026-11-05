<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SybAgriMicroFacility extends Model
{
    protected $table = 'syb_agri_micro_facilities';

    protected $fillable = [
        'facility_code',
        'farmer_party_ref',
        'plot_code',
        'commodity',
        'contract_type',
        'financing_amount_idr',
        'outstanding_balance_idr',
        'expected_yield_kg',
        'contract_price_per_kg_idr',
        'milestone_count',
        'disbursed_amount_idr',
        'ndvi_min_threshold_bps',
        'current_ndvi_bps',
        'status',
    ];

    protected $casts = [
        'financing_amount_idr' => 'integer',
        'outstanding_balance_idr' => 'integer',
        'expected_yield_kg' => 'integer',
        'contract_price_per_kg_idr' => 'integer',
        'milestone_count' => 'integer',
        'disbursed_amount_idr' => 'integer',
        'ndvi_min_threshold_bps' => 'integer',
        'current_ndvi_bps' => 'integer',
    ];

    public function milestones(): HasMany
    {
        return $this->hasMany(SybAgriMilestone::class, 'facility_code', 'facility_code');
    }
}
