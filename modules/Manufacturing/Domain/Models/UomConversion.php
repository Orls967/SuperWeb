<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UomConversion extends Model
{
    protected $table = 'mfg_uom_conversions';

    protected $fillable = ['material_id', 'from_uom', 'to_uom', 'factor'];

    protected $casts = ['factor' => 'decimal:8'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
