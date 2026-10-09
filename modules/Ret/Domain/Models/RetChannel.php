<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RetChannel extends Model
{
    protected $table = 'ret_channels';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'default_commission_pct' => 'float',
    ];
}
