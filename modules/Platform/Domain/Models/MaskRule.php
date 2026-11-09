<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MaskRule extends Model
{
    protected $table = 'plt_mask_rules';

    protected $fillable = [
        'domain',
        'column_pattern',
        'mask_strategy',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

