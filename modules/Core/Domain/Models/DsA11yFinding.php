<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DsA11yFinding extends Model
{
    protected $table = 'core_ds_a11y_findings';

    protected $fillable = [
        'component_id',
        'rule_violated',
        'severity',
        'description',
        'is_remediated',
        'remediated_at',
    ];

    protected $casts = [
        'is_remediated' => 'boolean',
        'remediated_at' => 'datetime',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(DsComponent::class, 'component_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_remediated', false);
    }

    public function markRemediated(): void
    {
        $this->update([
            'is_remediated' => true,
            'remediated_at' => now(),
        ]);
    }
}

