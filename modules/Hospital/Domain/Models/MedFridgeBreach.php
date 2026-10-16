<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedFridgeBreach extends Model
{
    protected $table = 'hsp_med_fridge_breaches';

    protected $fillable = [
        'breach_code',
        'fridge_unit_code',
        'lot_number',
        'recorded_temp_c',
        'supplier_id',
        'held_supplier_payable_idr',
        'status',
    ];

    protected $casts = [
        'recorded_temp_c' => 'decimal:1',
    ];
}
