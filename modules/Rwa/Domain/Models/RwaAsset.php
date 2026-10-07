<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RwaAsset extends Model
{
    protected $table = 'rwa_assets';

    protected $fillable = [
        'token_symbol',
        'name',
        'underlying_asset_type',
        'underlying_asset_id',
        'appraisal_value_idr',
        'total_supply_tokens',
        'token_price_idr',
        'status',
    ];

    protected $casts = [
        'appraisal_value_idr' => 'integer',
        'total_supply_tokens' => 'integer',
        'token_price_idr' => 'integer',
    ];

    public function holdings()
    {
        return $this->hasMany(RwaHolding::class, 'rwa_asset_id');
    }

    public function dividends()
    {
        return $this->hasMany(RwaDividend::class, 'rwa_asset_id');
    }
}
