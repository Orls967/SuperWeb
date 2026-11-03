<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class GatePass extends Model
{
    protected $table = 'prt_gate_passes';

    protected $fillable = [
        'pass_number',
        'container_no',
        'pass_type',
        'vehicle_reg_no',
        'line_party_id',
        'issued_at',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'completed_at' => 'datetime',
        'line_party_id' => 'integer',
    ];
}
