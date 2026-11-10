<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DsComponent extends Model
{
    protected $table = 'core_ds_components';

    protected $fillable = [
        'name',
        'component_name',
        'display_name',
        'category',
        'status',
        'current_version',
        'wcag_level',
        'spec_doc_url',
        'notes',
        'props_schema',
        'slots_schema',
        'events_schema',
        'owner_id',
        'idempotency_key',
        'requested_by_module',
        'reason',
        'replacement_component',
        'documentation_url',
    ];

    protected $casts = [
        'props_schema' => 'array',
        'slots_schema' => 'array',
        'events_schema' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (DsComponent $comp) {
            if (empty($comp->component_name) && ! empty($comp->name)) {
                $comp->component_name = $comp->name;
            }
            if (empty($comp->name) && ! empty($comp->component_name)) {
                $comp->name = $comp->component_name;
            }
            if (empty($comp->display_name)) {
                $comp->display_name = ucwords(str_replace('-', ' ', (string) $comp->name));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DsComponentHistory::class, 'component_id')->orderBy('created_at', 'asc');
    }

    public function a11yFindings(): HasMany
    {
        return $this->hasMany(DsA11yFinding::class, 'component_id');
    }

    public function openA11yFindings(): HasMany
    {
        return $this->hasMany(DsA11yFinding::class, 'component_id')->where('is_remediated', false);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(DsComponentUsage::class, 'component_name', 'name');
    }

    public function uxFindings(): HasMany
    {
        return $this->hasMany(UxFinding::class, 'component_id');
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', 'released');
    }
}
