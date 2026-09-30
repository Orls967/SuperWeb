<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IngredientMovement extends Model
{
    public $timestamps = false;

    protected $table = 'resto_ingredient_movements';

    protected $fillable = [
        'outlet_id',
        'ingredient_id',
        'qty_base_unit',
        'reason',
        'source_type',
        'source_id',
        'note',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'qty_base_unit' => 'string',
        'created_at' => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
