<?php

declare(strict_types=1);

namespace Modules\Wealth\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class WmHolding extends Model
{
    protected $table = 'wm_holdings';

    protected $fillable = [
        'profile_id',
        'asset_type',
        'value_idr',
    ];

    protected $casts = [
        'value_idr' => 'integer',
    ];

    public function profile()
    {
        return $this->belongsTo(WmProfile::class, 'profile_id');
    }
}
