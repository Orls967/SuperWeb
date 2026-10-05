<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasUuids;

    protected $table = 'trs_exchange_rates';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate_date',
        'rate_type',
        'rate_numerator',
        'rate_denominator',
        'source',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate_numerator' => 'integer',
        'rate_denominator' => 'integer',
    ];

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency', 'code');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency', 'code');
    }
}
