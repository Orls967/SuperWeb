<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchConsumption extends Model
{
    protected $table = 'resto_batch_consumptions';

    protected $fillable = [
        'batch_id',
        'ingredient_id',
        'qty_base_unit',
        'unit_cost',
        'line_cost',
    ];

    protected $casts = [
        'qty_base_unit' => 'string',
        'unit_cost' => 'string',
        'line_cost' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }
}
