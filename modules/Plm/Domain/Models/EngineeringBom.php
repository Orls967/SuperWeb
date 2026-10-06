<?php

declare(strict_types=1);

namespace Modules\Plm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringBom extends Model
{
    use HasUuids;

    protected $table = 'plm_engineering_boms';

    protected $fillable = [
        'project_id',
        'bom_number',
        'version',
        'components',
        'status',
    ];

    protected $casts = [
        'components' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlmProject::class, 'project_id');
    }

    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class, 'ebom_id');
    }
}
