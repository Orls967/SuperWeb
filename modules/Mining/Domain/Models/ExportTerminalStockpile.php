<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ExportTerminalStockpile extends Model
{
    protected $table = 'min_export_terminal_stockpiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'opening_tonnage' => 'float',
        'loaded_tonnage' => 'float',
        'remaining_tonnage' => 'float',
    ];
}
