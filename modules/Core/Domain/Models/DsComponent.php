<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DsComponent extends Model
{
    protected $table = 'core_ds_components';

    protected $fillable = [
        'component_name',
        'status',
        'requested_by_module',
        'reason',
        'replacement_component',
        'documentation_url',
    ];

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
        return $this->hasMany(DsComponentUsage::class, 'component_name', 'component_name');
    }

    public function uxFindings(): HasMany
    {
        return $this->hasMany(UxFinding::class, 'component_id');
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', 'released');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['removed']);
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function isDeprecated(): bool
    {
        return $this->status === 'deprecated';
    }

    public function isRemoved(): bool
    {
        return $this->status === 'removed';
    }

    /**
     * Menghitung total pemanggilan komponen di seluruh view aktif aplikasi.
     */
    public function getTotalUsageCount(): int
    {
        return (int) DsComponentUsage::where('component_name', $this->component_name)->sum('usage_count');
    }

    /**
     * Memeriksa apakah komponen memiliki temuan aksesibilitas yang masih belum diperbaiki.
     */
    public function hasOpenA11yFindings(): bool
    {
        return $this->openA11yFindings()->exists();
    }
}
