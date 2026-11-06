<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Riwayat Penyaluran/Pengisian Daya Baterai BESS.
 *
 * @property int $id
 * @property string $dispatch_number
 * @property int $bess_asset_id
 * @property string $dispatch_type
 * @property int $energy_kwh
 * @property int $charging_cost_idr
 * @property int $discharging_revenue_idr
 * @property int $degradation_cost_idr
 * @property int $net_profit_idr
 * @property int|null $ledger_transaction_id
 * @property string|null $certificate_hash
 */
final class BessDispatch extends Model
{
    protected $table = 'egy_bess_dispatches';

    protected $fillable = [
        'dispatch_number',
        'bess_asset_id',
        'dispatch_type',
        'energy_kwh',
        'charging_cost_idr',
        'discharging_revenue_idr',
        'degradation_cost_idr',
        'net_profit_idr',
        'ledger_transaction_id',
        'certificate_hash',
    ];

    protected $casts = [
        'bess_asset_id' => 'integer',
        'energy_kwh' => 'integer',
        'charging_cost_idr' => 'integer',
        'discharging_revenue_idr' => 'integer',
        'degradation_cost_idr' => 'integer',
        'net_profit_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];

    /**
     * @return BelongsTo<BessAsset, $this>
     */
    public function bessAsset(): BelongsTo
    {
        return $this->belongsTo(BessAsset::class, 'bess_asset_id');
    }
}
