<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Routing produksi: urutan operasi pada work center, ber-versi. */
class Routing extends Model
{
    use HasUuids;

    protected $table = 'mfg_routings';

    protected $fillable = [
        'output_material_id', 'version', 'name', 'effective_from', 'effective_to',
        'is_active', 'change_reason',
    ];

    protected $casts = [
        'version' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function outputMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'output_material_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'routing_id')->orderBy('sequence');
    }

    public function isEffective(?string $date = null): bool
    {
        $at = $date ?? now()->toDateString();

        return $this->is_active
            && $this->effective_from->toDateString() <= $at
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $at);
    }

    /** Total menit siklus per unit = Σ setup + run (setup dibagi perkiraan batch 1). */
    public function cycleMinutesPerUnit(): int
    {
        return (int) $this->operations->sum(
            fn (RoutingOperation $op) => $op->setup_minutes + $op->run_minutes_per_unit
        );
    }
}
