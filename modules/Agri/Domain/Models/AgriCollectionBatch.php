<?php

namespace Modules\Agri\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriCollectionBatch extends Model
{
    use HasUuids;

    protected $table = 'agri_collection_batches';

    protected $fillable = [
        'batch_number',
        'collection_center_id',
        'contract_id',
        'received_date',
        'gross_weight_kg',
        'grade',
        'moisture_percentage',
        'buying_price_per_kg',
        'gross_payout_idr',
        'advance_deduction_idr',
        'net_payout_idr',
        'payment_status',
        'destination_unit',
    ];

    protected $casts = [
        'received_date' => 'date',
        'gross_weight_kg' => 'decimal:2',
        'moisture_percentage' => 'decimal:2',
        'buying_price_per_kg' => 'integer',
        'gross_payout_idr' => 'integer',
        'advance_deduction_idr' => 'integer',
        'net_payout_idr' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(AgriContract::class, 'contract_id');
    }

    public function coldChainLogs(): HasMany
    {
        return $this->hasMany(AgriColdChainLog::class, 'batch_id');
    }
}
