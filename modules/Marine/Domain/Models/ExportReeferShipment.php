<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExportReeferShipment extends Model
{
    protected $table = 'mar_export_shipments';

    protected $fillable = [
        'shipment_tracking_number',
        'export_order_id',
        'export_lot_id',
        'container_iso_number',
        'shipping_line_carrier',
        'booking_reference',
        'ocean_bill_of_lading',
        'health_certificate_official_number',
        'temperature_setpoint_celsius',
        'stuffed_and_sealed_at',
        'estimated_departure_at',
        'estimated_arrival_at',
        'shipment_status',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(AquacultureExportOrder::class, 'export_order_id');
    }

    public function exportLot(): BelongsTo
    {
        return $this->belongsTo(AquacultureExportLot::class, 'export_lot_id');
    }

    public function coldChainLogs(): HasMany
    {
        return $this->hasMany(ColdChainTelemetryLog::class, 'export_shipment_id');
    }
}
