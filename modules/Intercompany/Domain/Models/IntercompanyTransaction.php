<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IntercompanyTransaction extends Model
{
    protected $table = 'ic_transactions';

    protected $guarded = [];

    protected $casts = [
        'amount_idr' => 'integer',
    ];
}
