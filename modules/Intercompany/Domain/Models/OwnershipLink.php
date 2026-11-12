<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OwnershipLink extends Model
{
    protected $table = 'ic_ownership_links';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'parent_entity_code',
        'child_entity_code',
        'ownership_pct_bps',
        'consolidation_method',
        'effective_date',
        'is_active',
    ];

    protected $casts = [
        'ownership_pct_bps' => 'integer',
        'is_active' => 'boolean',
        'effective_date' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
