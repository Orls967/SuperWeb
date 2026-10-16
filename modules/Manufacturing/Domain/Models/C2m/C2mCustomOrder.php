<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\C2m;

use Illuminate\Database\Eloquent\Model;

class C2mCustomOrder extends Model
{
    protected $table = 'mfg_c2m_custom_orders';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array',
        'complexity_factor' => 'decimal:2',
    ];
}
