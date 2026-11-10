<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FxLot extends Model
{
    use HasUuids;

    protected $table = 'trs_fx_lots';

    protected $fillable = [
        'entity_id',
        'currency',
        'lot_date',
        'original_amount',
        'remaining_amount',
        'original_rate_num',
        'original_rate_den',
        'original_cost_idr',
        'current_carrying_rate_num',
        'current_carrying_rate_den',
        'current_carrying_idr',
        'last_revaluation_id',
        'status',
        'source_ref',
    ];

    protected $casts = [
        'lot_date' => 'date',
        'original_amount' => 'integer',
        'remaining_amount' => 'integer',
        'original_rate_num' => 'integer',
        'original_rate_den' => 'integer',
        'original_cost_idr' => 'integer',
        'current_carrying_rate_num' => 'integer',
        'current_carrying_rate_den' => 'integer',
        'current_carrying_idr' => 'integer',
    ];

    protected function carryingFunctionalIdr(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) $this->current_carrying_idr
        );
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function revaluation(): BelongsTo
    {
        return $this->belongsTo(Revaluation::class, 'last_revaluation_id');
    }

    public function revaluations(): HasMany
    {
        return $this->hasMany(Revaluation::class, 'currency', 'currency');
    }
}
