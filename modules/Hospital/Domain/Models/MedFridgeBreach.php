<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedFridgeBreach extends Model
{
    protected $table = 'hsp_med_fridge_breaches';

    protected $guarded = [];

    protected $casts = [
        'recorded_temp_c' => 'decimal:1',
    ];
}
