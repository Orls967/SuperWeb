<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AquacultureExportLot extends Model
{
    protected $table = 'mar_export_lots';

    protected $fillable = [
        'lot_tracking_number',
        'harvest_operation_id',
        'product_form',
        'commercial_grade',
        'size_classification',
        'net_lot_weight_kg',
        'pack_count_cartons',
        'defects_organoleptic_pct',
        'estimated_export_value_minor_cents',
        'freeze_method',
        'storage_room_code',
    ];

    public function harvestOperation(): BelongsTo
    {
        return $this->belongsTo(AquacultureHarvestOperation::class, 'harvest_operation_id');
    }

    public function processingPackaging(): HasOne
    {
        return $this->hasOne(ProcessingPackaging::class, 'export_lot_id');
    }

    public function laboratoryAssays(): HasMany
    {
        return $this->hasMany(LaboratoryAssay::class, 'export_lot_id');
    }

    public function traceabilityNodes(): HasMany
    {
        return $this->hasMany(TraceabilityLotNode::class, 'export_lot_id');
    }

    public function exportShipments(): HasMany
    {
        return $this->hasMany(ExportReeferShipment::class, 'export_lot_id');
    }
}
