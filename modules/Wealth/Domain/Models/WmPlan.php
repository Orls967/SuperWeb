<?php

declare(strict_types=1);

namespace Modules\Wealth\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WmPlan extends Model
{
    protected $table = 'wm_plans';

    protected $fillable = [
        'profile_id',
        'allocation_mutual_funds_pct',
        'allocation_gold_pct',
        'allocation_crypto_pct',
    ];

    protected $casts = [
        'allocation_mutual_funds_pct' => 'integer',
        'allocation_gold_pct' => 'integer',
        'allocation_crypto_pct' => 'integer',
    ];

    public function profile()
    {
        return $this->belongsTo(WmProfile::class, 'profile_id');
    }
}
