<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FleetPortCall extends Model
{
    protected $table = 'prt_fleet_port_calls';

    protected $fillable = [
        'voyage_id',
        'port_code',
        'port_name',
        'call_purpose',
        'estimated_arrival_at',
        'actual_arrival_at',
        'nor_tendered_at',
        'nor_accepted_at',
        'all_fast_at',
        'commenced_cargo_at',
        'completed_cargo_at',
        'actual_departure_at',
        'arrival_draft_forward_m',
        'arrival_draft_aft_m',
        'departure_draft_forward_m',
        'departure_draft_aft_m',
        'call_status',
    ];

    protected $casts = [
        'estimated_arrival_at' => 'datetime',
        'actual_arrival_at' => 'datetime',
        'nor_tendered_at' => 'datetime',
        'nor_accepted_at' => 'datetime',
        'all_fast_at' => 'datetime',
        'commenced_cargo_at' => 'datetime',
        'completed_cargo_at' => 'datetime',
        'actual_departure_at' => 'datetime',
        'arrival_draft_forward_m' => 'float',
        'arrival_draft_aft_m' => 'float',
        'departure_draft_forward_m' => 'float',
        'departure_draft_aft_m' => 'float',
    ];

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(FleetVoyage::class, 'voyage_id');
    }

    public function pilotageRequests(): HasMany
    {
        return $this->hasMany(FleetPilotageRequest::class, 'port_call_id');
    }

    public function tugServices(): HasMany
    {
        return $this->hasMany(FleetTugService::class, 'port_call_id');
    }

    public function berthBookings(): HasMany
    {
        return $this->hasMany(FleetBerthBooking::class, 'port_call_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(FleetPortDisbursement::class, 'port_call_id');
    }

    public function wasteDisposals(): HasMany
    {
        return $this->hasMany(FleetWasteDisposal::class, 'port_call_id');
    }

    public function bunkeringOperations(): HasMany
    {
        return $this->hasMany(FleetBunkeringOperation::class, 'port_call_id');
    }
}

