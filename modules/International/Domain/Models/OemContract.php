<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OemContract extends Model
{
    protected $table = 'intl_oem_contracts';

    protected $fillable = [
        'contract_number',
        'foreign_entity_id',
        'type',
        'product_design_name',
        'tolling_fee_per_unit_idr',
        'consignment_materials_tracked',
        'nda_signed',
        'qa_standard',
        'status',
    ];

    protected $casts = [
        'tolling_fee_per_unit_idr' => 'integer',
        'consignment_materials_tracked' => 'boolean',
        'nda_signed' => 'boolean',
    ];

    public function foreignEntity(): BelongsTo
    {
        return $this->belongsTo(ForeignEntity::class, 'foreign_entity_id');
    }
}
