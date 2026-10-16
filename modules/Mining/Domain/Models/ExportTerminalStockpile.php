<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ExportTerminalStockpile extends Model
{
    protected $table = 'min_export_terminal_stockpiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'terminal_code',
        'stockpile_code',
        'commodity',
        'opening_tonnage',
        'loaded_tonnage',
        'remaining_tonnage',
    ];

    protected $casts = [
        'opening_tonnage' => 'float',
        'loaded_tonnage' => 'float',
        'remaining_tonnage' => 'float',
    ];
}
