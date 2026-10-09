<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hak guna aset & liabilitas sewa (PSAK 73 simulasi) dari Contract Fase 29.
 */
class AssetLease extends Model
{
    protected $table = 'ast_leases';

    protected $fillable = [
        'asset_id', 'contract_id', 'lease_kind', 'right_of_use_asset_idr', 'lease_liability_idr',
        'periodic_payment_idr', 'total_periods', 'elapsed_periods', 'amortized_interest_idr',
        'implicit_rate', 'start_date', 'end_date', 'status',
    ];

    protected $casts = [
        'right_of_use_asset_idr' => 'integer', 'lease_liability_idr' => 'integer',
        'periodic_payment_idr' => 'integer', 'total_periods' => 'integer',
        'elapsed_periods' => 'integer', 'amortized_interest_idr' => 'integer',
        'implicit_rate' => 'decimal:4', 'start_date' => 'date', 'end_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AssetLeasePayment::class, 'lease_id')->orderBy('period_no');
    }
}
