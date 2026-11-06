<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Microgrid lokal kawasan.
 *
 * @property int $id
 * @property string $microgrid_code
 * @property string $site_name
 * @property string $operating_mode
 * @property int $total_capacity_kw
 * @property int $current_generation_kw
 * @property int $current_load_kw
 * @property int $grid_frequency_mhz
 * @property int $bus_voltage_volt
 * @property bool $is_black_start_capable
 */
final class Microgrid extends Model
{
    protected $table = 'egy_microgrids';

    protected $fillable = [
        'microgrid_code',
        'site_name',
        'operating_mode',
        'total_capacity_kw',
        'current_generation_kw',
        'current_load_kw',
        'grid_frequency_mhz',
        'bus_voltage_volt',
        'is_black_start_capable',
    ];

    protected $casts = [
        'total_capacity_kw' => 'integer',
        'current_generation_kw' => 'integer',
        'current_load_kw' => 'integer',
        'grid_frequency_mhz' => 'integer',
        'bus_voltage_volt' => 'integer',
        'is_black_start_capable' => 'boolean',
    ];

    /**
     * @return HasMany<MicrogridFeeder, $this>
     */
    public function feeders(): HasMany
    {
        return $this->hasMany(MicrogridFeeder::class, 'microgrid_id');
    }

    /**
     * @return HasMany<BlackStartPlan, $this>
     */
    public function blackStartPlans(): HasMany
    {
        return $this->hasMany(BlackStartPlan::class, 'microgrid_id');
    }
}
