<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TimeshareUnit extends Model
{
    protected $table = 'htl_timeshare_units';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_id',
        'unit_code',
        'unit_name',
        'total_token_shares',
        'daily_rental_rate_idr',
    ];

    protected $casts = [
        'total_token_shares' => 'integer',
        'daily_rental_rate_idr' => 'integer',
    ];
}
