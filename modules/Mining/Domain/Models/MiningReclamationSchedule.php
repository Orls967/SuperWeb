<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningReclamationSchedule extends Model
{
    use HasUuids;

    protected $table = 'min_reclamation_schedules';

    protected $fillable = [
        'project_number',
        'site_id',
        'phase_name',
        'planned_year',
        'planned_quarter',
        'target_area_hectares',
        'completed_area_hectares',
        'provision_amount_minor',
        'unwound_amount_minor',
        'guarantee_bond_amount_minor',
        'status',
    ];

    protected $casts = [
        'planned_year' => 'integer',
        'planned_quarter' => 'integer',
        'target_area_hectares' => 'integer',
        'completed_area_hectares' => 'integer',
        'provision_amount_minor' => 'integer',
        'unwound_amount_minor' => 'integer',
        'guarantee_bond_amount_minor' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function remainingProvisionMinor(): int
    {
        return (int) max(0, $this->provision_amount_minor - $this->unwound_amount_minor);
    }

    public function getAreaNameAttribute(): string
    {
        return $this->phase_name ?? '';
    }

    public function getEstimatedCostIdrAttribute(): int
    {
        return (int) ($this->provision_amount_minor ?? 0);
    }

    public function getTargetHectaresAttribute(): float
    {
        return (float) ($this->target_area_hectares ?? 0);
    }
}
