<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Manifest Pengiriman Rujukan Lab Rekanan Eksternal (Fase 105).
 *
 * @property int $id
 * @property string $manifest_number
 * @property int $contract_id
 * @property int $specimen_count
 * @property string $courier_batch_barcode
 * @property float $departure_temperature_celsius
 * @property float|null $arrival_temperature_celsius
 * @property string $handover_hash
 * @property string $status
 */
class LabOutsourcingManifest extends Model
{
    use HasFactory;

    protected $table = 'hsp_lab_outsourcing_manifests';

    protected $fillable = [
        'manifest_number',
        'contract_id',
        'specimen_count',
        'courier_batch_barcode',
        'departure_temperature_celsius',
        'arrival_temperature_celsius',
        'handover_hash',
        'status',
    ];

    protected $casts = [
        'specimen_count' => 'integer',
        'departure_temperature_celsius' => 'float',
        'arrival_temperature_celsius' => 'float',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LabPartnerContract::class, 'contract_id');
    }
}
