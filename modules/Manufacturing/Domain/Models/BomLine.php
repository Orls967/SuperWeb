<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    protected $table = 'mfg_bom_lines';

    protected $fillable = [
        'bom_id', 'input_material_id', 'qty', 'uom', 'scrap_percent', 'is_alternative',
        'substitution_group', 'is_by_product', 'is_co_product', 'allocation_percent', 'sequence',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'scrap_percent' => 'decimal:4', 'is_alternative' => 'boolean',
        'is_by_product' => 'boolean', 'is_co_product' => 'boolean', 'allocation_percent' => 'decimal:4',
        'sequence' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function inputMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'input_material_id');
    }
}
