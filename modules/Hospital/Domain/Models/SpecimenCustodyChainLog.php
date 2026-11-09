<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model Rantai Kustodi Spesimen 3-Hop Berbasis Hash-Chain SHA-256 (Fase 105).
 *
 * @property int $id
 * @property int $specimen_id
 * @property int $hop_number
 * @property string $from_handler
 * @property string $to_handler
 * @property float|null $transport_temp_celsius
 * @property string|null $gps_coordinates
 * @property string|null $storage_container_barcode
 * @property string $prev_hash
 * @property string $custody_hash
 * @property string|null $notes
 * @property Carbon $transferred_at
 */
class SpecimenCustodyChainLog extends Model
{
    use HasFactory;

    protected $table = 'hsp_specimen_custody_chain_logs';

    protected $fillable = [
        'specimen_id',
        'hop_number',
        'from_handler',
        'to_handler',
        'transport_temp_celsius',
        'gps_coordinates',
        'storage_container_barcode',
        'prev_hash',
        'custody_hash',
        'notes',
        'transferred_at',
    ];

    protected $casts = [
        'hop_number' => 'integer',
        'transport_temp_celsius' => 'float',
        'transferred_at' => 'datetime',
    ];

    /**
     * Relasi ke spesimen induk.
     */
    public function specimen(): BelongsTo
    {
        return $this->belongsTo(LabSpecimen::class, 'specimen_id');
    }

    /**
     * Menghitung ulang hash kanonikal simpul ini.
     */
    public function calculateCanonicalHash(): string
    {
        $payload = implode('|', [
            $this->prev_hash,
            (string) $this->specimen_id,
            (string) $this->hop_number,
            $this->from_handler,
            $this->to_handler,
            $this->transport_temp_celsius !== null ? number_format($this->transport_temp_celsius, 2, '.', '') : 'NA',
            $this->transferred_at ? $this->transferred_at->toIso8601String() : 'NA',
        ]);

        return hash('sha256', $payload);
    }
}
