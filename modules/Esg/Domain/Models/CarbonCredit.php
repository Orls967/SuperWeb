<?php

namespace Modules\Esg\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarbonCredit extends Model
{
    use HasUuids;

    protected $table = 'esg_carbon_credits';

    protected $fillable = [
        'certificate_number',
        'registry',
        'project_name',
        'project_type',
        'vintage_year',
        'quantity_co2e_tons',
        'cost_per_ton_idr',
        'total_cost_idr',
        'status',
    ];

    protected $casts = [
        'quantity_co2e_tons' => 'decimal:4',
        'cost_per_ton_idr' => 'decimal:2',
        'total_cost_idr' => 'integer',
        'vintage_year' => 'integer',
    ];

    public function retirements(): HasMany
    {
        return $this->hasMany(OffsetRetirement::class, 'carbon_credit_id');
    }
}
