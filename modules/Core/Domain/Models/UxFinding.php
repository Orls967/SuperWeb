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
        'iteration_id',
        'component_id',
        'finding_code',
        'module_code',
        'category',
        'severity',
        'description',
        'recommendation',
        'is_resolved',
        'resolved_at',
        'status',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (UxFinding $f) {
            if (empty($f->finding_code)) {
                $f->finding_code = 'UX-FIND-'.strtoupper(substr(md5($f->description ?? (string) time()), 0, 8));
            }
        });
    }

    public function iteration(): BelongsTo
    {
        return $this->belongsTo(UxIteration::class, 'iteration_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(DsComponent::class, 'component_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }
}
