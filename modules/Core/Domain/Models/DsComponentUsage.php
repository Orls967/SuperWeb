<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DsComponentUsage extends Model
{
    public $timestamps = false;

    protected $table = 'core_ds_component_usage';

    protected $fillable = [
        'component_name',
        'view_path',
        'usage_count',
        'last_scanned_at',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'last_scanned_at' => 'datetime',
    ];

    public function scopeForComponent(Builder $query, string $componentName): Builder
    {
        return $query->where('component_name', $componentName);
    }

    public function scopeForView(Builder $query, string $viewPath): Builder
    {
        return $query->where('view_path', $viewPath);
    }
}

