<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetGeneralAverageCase extends Model
{
    protected $table = 'prt_fleet_general_average_cases';

    protected $fillable = [
        'voyage_id',
        'case_number',
        'incident_description',
        'declared_at',
        'average_adjuster_firm',
        'ship_contributory_value_usd',
        'cargo_contributory_value_usd',
        'freight_contributory_value_usd',
        'total_contributory_value_usd',
        'total_sacrifices_usd',
        'total_expenditures_usd',
        'general_average_loss_usd',
        'contribution_rate_percentage',
        'ship_contribution_usd',
        'cargo_contribution_usd',
        'freight_contribution_usd',
        'adjustment_status',
    ];

    protected $casts = [
        'declared_at' => 'datetime',
        'ship_contributory_value_usd' => 'float',
        'cargo_contributory_value_usd' => 'float',
        'freight_contributory_value_usd' => 'float',
        'total_contributory_value_usd' => 'float',
        'total_sacrifices_usd' => 'float',
        'total_expenditures_usd' => 'float',
        'general_average_loss_usd' => 'float',
        'contribution_rate_percentage' => 'float',
        'ship_contribution_usd' => 'float',
        'cargo_contribution_usd' => 'float',
        'freight_contribution_usd' => 'float',
    ];

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(FleetVoyage::class, 'voyage_id');
    }
}
