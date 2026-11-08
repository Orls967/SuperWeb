<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AquacultureBatch extends Model
{
    protected $table = 'mar_aquaculture_batches';

    protected $fillable = [
        'batch_code',
        'pond_id',
        'hatchery_id',
        'species_scientific_name',
        'commercial_name',
        'initial_stock_count',
        'initial_density_per_m2',
        'initial_average_body_weight_grams',
        'stocking_date',
        'expected_harvest_date',
        'target_harvest_weight_grams',
        'certification_scope',
        'stage',
        'status',
    ];

    public function pond(): BelongsTo
    {
        return $this->belongsTo(MarinePond::class, 'pond_id');
    }

    public function hatchery(): BelongsTo
    {
        return $this->belongsTo(AquacultureHatchery::class, 'hatchery_id');
    }

    public function waterQualityTelemetries(): HasMany
    {
        return $this->hasMany(WaterQualityTelemetry::class, 'batch_id');
    }

    public function feedingEvents(): HasMany
    {
        return $this->hasMany(FeedingEvent::class, 'batch_id');
    }

    public function diseaseHealthScreenings(): HasMany
    {
        return $this->hasMany(DiseaseHealthScreening::class, 'batch_id');
    }

    public function harvestOperations(): HasMany
    {
        return $this->hasMany(AquacultureHarvestOperation::class, 'batch_id');
    }

    public function eudrCertification(): HasOne
    {
        return $this->hasOne(EudrDeforestationCertification::class, 'batch_id');
    }
}
