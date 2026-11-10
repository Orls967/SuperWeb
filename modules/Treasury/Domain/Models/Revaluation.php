<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Revaluation extends Model
{
    use HasUuids;

    protected $table = 'trs_revaluations';

    protected $fillable = [
        'period',
        'currency',
        'foreign_balance',
        'book_functional_idr',
        'revalued_functional_idr',
        'gain_loss_idr',
        'ledger_transaction_id',
        'status',
        'reverse_of',
        'is_reversed',
        'reversed_at',
        'certificate',
    ];

    protected $casts = [
        'foreign_balance' => 'integer',
        'book_functional_idr' => 'integer',
        'revalued_functional_idr' => 'integer',
        'gain_loss_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
        'is_reversed' => 'boolean',
        'reversed_at' => 'datetime',
        'certificate' => 'array',
    ];

    protected function revaluedCarryingIdr(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) $this->revalued_functional_idr
        );
    }

    public function originalRevaluation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverse_of');
    }

    public function reversalRecords(): HasMany
    {
        return $this->hasMany(self::class, 'reverse_of');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }
}
