<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingPackaging extends Model
{
    protected $table = 'mar_processing_packagings';

    protected $fillable = [
        'packaging_slip_number',
        'export_lot_id',
        'processing_plant_code',
        'haccp_plan_reference',
        'core_temperature_celsius',
        'glazing_water_percentage',
        'barcode_gtin14',
        'packaging_material',
        'packed_at',
        'best_before_date',
        'sanitation_inspector_name',
    ];

    public function exportLot(): BelongsTo
    {
        return $this->belongsTo(AquacultureExportLot::class, 'export_lot_id');
    }
}
