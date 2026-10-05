<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Tier hak diskon (Bronze/Silver/Gold). */
class Tier extends Model
{
    protected $table = 'dist_tiers';

    protected $fillable = ['code', 'label', 'discount_percent', 'min_achievement_percent', 'is_active'];

    protected $casts = [
        'discount_percent' => 'decimal:4', 'min_achievement_percent' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
