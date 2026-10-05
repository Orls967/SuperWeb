<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OemContract extends Model
{
    protected $table = 'intl_oem_contracts';

    protected $guarded = [];

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
