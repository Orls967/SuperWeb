<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\BatchStatus;

class ProductionBatch extends Model
{
    protected $table = 'resto_production_batches';

    protected $fillable = [
        'uuid',
        'outlet_id',
        'recipe_id',
        'menu_item_id',
        'batch_no',
        'planned_portions',
        'actual_portions',
        'cooked_at',
        'expires_at',
        'status',
        'cost_total',
        'cost_per_portion',
        'produced_by',
        'note',
    ];

    protected $casts = [
        'status' => BatchStatus::class,
        'planned_portions' => 'integer',
        'actual_portions' => 'integer',
        'cost_total' => 'integer',
        'cost_per_portion' => 'integer',
        'cooked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductionBatch $batch) {
            if (empty($batch->uuid)) {
                $batch->uuid = (string) Str::uuid();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function producedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(BatchConsumption::class, 'batch_id');
    }

    public function trays(): HasMany
    {
        return $this->hasMany(DisplayTray::class, 'batch_id');
    }

    public function variancePortions(): int
    {
        return $this->actual_portions - $this->planned_portions;
    }

    public function canTransitionTo(BatchStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }
}
