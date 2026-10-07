<?php

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FrozenQuote extends Model
{
    protected $table = 'prc_frozen_quotes';

    protected $guarded = [];

    protected $casts = [
        'locked_until' => 'datetime',
    ];
}
