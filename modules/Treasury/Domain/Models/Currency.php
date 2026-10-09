<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'trs_currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'minor_units',
        'is_active',
    ];

    protected $casts = [
        'minor_units' => 'integer',
        'is_active' => 'boolean',
    ];
}
