<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** BOM multi-level ber-versi dengan jendela efektif. */
class Bom extends Model
{
    use HasUuids;

    protected $table = 'mfg_boms';

    protected $fillable = [
        'output_material_id', 'version', 'name', 'output_qty', 'output_uom',
        'effective_from', 'effective_to', 'is_active', 'change_reason', 'prev_hash', 'hash',
    ];

    protected $casts = [
        'version' => 'integer', 'output_qty' => 'decimal:6', 'effective_from' => 'date',
        'effective_to' => 'date', 'is_active' => 'boolean',
    ];

    public function outputMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'output_material_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class, 'bom_id')->orderBy('sequence');
    }

    public function isEffective(?string $date = null): bool
    {
        $at = $date ?? now()->toDateString();

        return $this->is_active
            && $this->effective_from->toDateString() <= $at
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $at);
    }
}
