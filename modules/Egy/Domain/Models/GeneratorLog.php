<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Pencatatan Pengujian dan Operasional Genset Darurat.
 *
 * @property int $id
 * @property string $generator_asset_code
 * @property string $site_name
 * @property string $run_type
 * @property Carbon $start_time
 * @property Carbon|null $end_time
 * @property int $duration_minutes
 * @property int $fuel_consumed_liters
 * @property int $load_percentage_bps
 * @property int $emissions_kg_co2e
 * @property bool $is_passed
 * @property string|null $notes
 */
final class GeneratorLog extends Model
{
    protected $table = 'egy_generator_logs';

    protected $fillable = [
        'generator_asset_code',
        'site_name',
        'run_type',
        'start_time',
        'end_time',
        'duration_minutes',
        'fuel_consumed_liters',
        'load_percentage_bps',
        'emissions_kg_co2e',
        'is_passed',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'fuel_consumed_liters' => 'integer',
        'load_percentage_bps' => 'integer',
        'emissions_kg_co2e' => 'integer',
        'is_passed' => 'boolean',
    ];
}
