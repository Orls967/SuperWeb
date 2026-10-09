<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BanquetProductionSheet extends Model
{
    protected $table = 'htl_banquet_production_sheets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'mice_contract_id',
        'menu_package_name',
        'pax_count',
        'fnb_materials_cost_idr',
        'av_equipment_vendor_cost_idr',
        'decor_florist_vendor_cost_idr',
        'total_production_cost_idr',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MiceContract::class, 'mice_contract_id');
    }
}
