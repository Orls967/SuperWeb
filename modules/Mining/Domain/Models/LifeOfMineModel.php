<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class LifeOfMineModel extends Model
{
    protected $table = 'min_life_of_mine_models';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'site_id',
        'model_code',
        'proven_reserves_tonnage',
        'annual_run_rate_tonnage',
        'life_of_mine_years',
        'projected_expansion_capex_minor',
        'capex_dao_approved',
    ];

    protected $casts = [
        'proven_reserves_tonnage' => 'float',
        'annual_run_rate_tonnage' => 'float',
        'life_of_mine_years' => 'float',
        'projected_expansion_capex_minor' => 'integer',
        'capex_dao_approved' => 'boolean',
    ];
}
