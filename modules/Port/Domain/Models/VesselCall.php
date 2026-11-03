<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VesselCall extends Model
{
    protected $table = 'prt_vessel_calls';

    protected $fillable = [
        'call_number',
        'vessel_name',
        'imo_number',
        'line_party_id',
        'eta',
        'etd',
        'ata',
        'atd',
        'berth_id',
        'status',
        'weight',
        'duration_hours',
        'schedule_certificate',
        'metadata',
    ];

    protected $casts = [
        'eta' => 'datetime',
        'etd' => 'datetime',
        'ata' => 'datetime',
        'atd' => 'datetime',
        'weight' => 'integer',
        'duration_hours' => 'integer',
        'schedule_certificate' => 'array',
        'metadata' => 'array',
    ];

    public function berth(): BelongsTo
    {
        return $this->belongsTo(Berth::class, 'berth_id');
    }

    public function terminalBills(): HasMany
    {
        return $this->hasMany(TerminalBill::class, 'vessel_call_id');
    }
}
