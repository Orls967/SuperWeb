<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Formula/resep: draft → approval → approved, hash-chain antar versi. */
class Formula extends Model
{
    use HasUuids;

    protected $table = 'mfg_formulas';

    protected $fillable = [
        'output_material_id', 'version', 'name', 'standard_yield_percent',
        'yield_tolerance_percent', 'active_ingredients', 'effective_from',
        'effective_to', 'status', 'approval_id', 'prev_hash', 'hash', 'change_reason',
    ];

    protected $casts = [
        'version' => 'integer',
        'standard_yield_percent' => 'decimal:4',
        'yield_tolerance_percent' => 'decimal:4',
        'active_ingredients' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approval_id' => 'integer',
    ];

    public function outputMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'output_material_id');
    }

    public function isEffective(?string $date = null): bool
    {
        $at = $date ?? now()->toDateString();

        return $this->status === 'approved'
            && $this->effective_from->toDateString() <= $at
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $at);
    }
}
