<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UxFinding extends Model
{
    protected $table = 'core_ux_findings';

    protected $fillable = [
        'finding_code',
        'module_code',
        'severity',
        'description',
        'status',
        'component_id',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(DsComponent::class, 'component_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'OPEN');
    }

    public function scopeBacklog(Builder $query): Builder
    {
        return $query->where('status', 'BACKLOG');
    }

    public function moveToBacklog(): void
    {
        $this->update(['status' => 'BACKLOG']);
    }

    public function markRemediated(): void
    {
        $this->update(['status' => 'REMEDIATED']);
    }
}
