<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EliminationEntry extends Model
{
    protected $table = 'ic_elimination_entries';

    protected $guarded = [];

    protected $casts = [
        'amount_idr' => 'integer',
    ];
}
