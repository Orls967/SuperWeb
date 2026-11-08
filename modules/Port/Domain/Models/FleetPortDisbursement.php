<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetPortDisbursement extends Model
{
    protected $table = 'prt_fleet_port_disbursements';

    protected $fillable = [
        'port_call_id',
        'disbursement_type',
        'account_reference',
        'port_dues_usd',
        'pilotage_usd',
        'towage_usd',
        'berth_hire_usd',
        'linesmen_usd',
        'waste_reception_usd',
        'agency_fee_usd',
        'customs_immigration_usd',
        'total_disbursement_usd',
        'variance_with_pda_usd',
        'status',
    ];

    protected $casts = [
        'port_dues_usd' => 'float',
        'pilotage_usd' => 'float',
        'towage_usd' => 'float',
        'berth_hire_usd' => 'float',
        'linesmen_usd' => 'float',
        'waste_reception_usd' => 'float',
        'agency_fee_usd' => 'float',
        'customs_immigration_usd' => 'float',
        'total_disbursement_usd' => 'float',
        'variance_with_pda_usd' => 'float',
    ];

    public function portCall(): BelongsTo
    {
        return $this->belongsTo(FleetPortCall::class, 'port_call_id');
    }
}
