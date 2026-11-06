<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Aset Sistem Baterai Penyimpanan Energi (BESS).
 *
 * @property int $id
 * @property string $bess_code
 * @property string $site_name
 * @property string $battery_chemistry
 * @property int $capacity_kwh
 * @property int $max_charge_power_kw
 * @property int $max_discharge_power_kw
 * @property int $current_soc_bps
 * @property int $state_of_health_bps
 * @property int $cycle_count
 * @property int $replacement_cost_idr
 * @property string $status
 */
final class BessAsset extends Model
{
    protected $table = 'egy_bess_assets';

    protected $fillable = [
        'bess_code',
        'site_name',
        'battery_chemistry',
        'capacity_kwh',
        'max_charge_power_kw',
        'max_discharge_power_kw',
        'current_soc_bps',
        'state_of_health_bps',
        'cycle_count',
        'replacement_cost_idr',
        'status',
    ];

    protected $casts = [
        'capacity_kwh' => 'integer',
        'max_charge_power_kw' => 'integer',
        'max_discharge_power_kw' => 'integer',
        'current_soc_bps' => 'integer',
        'state_of_health_bps' => 'integer',
        'cycle_count' => 'integer',
        'replacement_cost_idr' => 'integer',
    ];

    /**
     * @return HasMany<BessDispatch, $this>
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(BessDispatch::class, 'bess_asset_id');
    }
}
