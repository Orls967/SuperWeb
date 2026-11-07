<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningQuarryDroneSurvey extends Model
{
    use HasUuids;

    protected $table = 'min_quarry_drone_surveys';

    protected $fillable = [
        'id',
        'site_id',
        'stockpile_id',
        'quarry_pit_code',
        'quarry_pit_id',
        'survey_code',
        'survey_type',
        'drone_model',
        'pilot_license_number',
        'ground_control_points_count',
        'horizontal_accuracy_cm',
        'vertical_accuracy_cm',
        'ground_sampling_distance_cm_px',
        'total_points_count',
        'surveyed_area_hectares',
        'flight_date',
        'flight_altitude_m',
        'photos_count',
        'point_cloud_density_pts_m2',
        'calculated_volume_m3',
        'calculated_cut_volume_m3',
        'calculated_fill_volume_m3',
        'calculated_net_volume_m3',
        'estimated_density_t_m3',
        'calculated_tonnage',
        'calculated_tonnage_wmt',
        'weighbridge_tonnage',
        'variance_percent',
        'status',
        'pilot_name',
        'surveyed_at',
        'metadata',
    ];

    protected $casts = [
        'ground_control_points_count' => 'integer',
        'horizontal_accuracy_cm' => 'float',
        'vertical_accuracy_cm' => 'float',
        'ground_sampling_distance_cm_px' => 'float',
        'total_points_count' => 'integer',
        'surveyed_area_hectares' => 'float',
        'flight_date' => 'date',
        'flight_altitude_m' => 'integer',
        'photos_count' => 'integer',
        'point_cloud_density_pts_m2' => 'float',
        'calculated_volume_m3' => 'float',
        'calculated_cut_volume_m3' => 'float',
        'calculated_fill_volume_m3' => 'float',
        'calculated_net_volume_m3' => 'float',
        'estimated_density_t_m3' => 'float',
        'calculated_tonnage' => 'float',
        'calculated_tonnage_wmt' => 'float',
        'weighbridge_tonnage' => 'float',
        'variance_percent' => 'float',
        'surveyed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getQuarryPitIdAttribute(): ?string
    {
        return $this->quarry_pit_code;
    }

    public function setQuarryPitIdAttribute(?string $value): void
    {
        $this->attributes['quarry_pit_code'] = $value;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function stockpile(): BelongsTo
    {
        return $this->belongsTo(MiningStockpile::class, 'stockpile_id');
    }
}
