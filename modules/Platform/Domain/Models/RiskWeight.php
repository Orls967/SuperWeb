<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskWeight extends Model
{
    use HasFactory;

    protected $table = 'plt_risk_weights';

    protected $fillable = [
        'factor_key',
        'weight_bps',
        'description',
        'metadata',
    ];

    protected $casts = [
        'weight_bps' => 'integer',
        'metadata' => 'array',
    ];
}
