<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteCustodyLog extends Model
{
    protected $table = 'hsp_waste_custody_logs';

    protected $fillable = [
        'manifest_id',
        'event_type',
        'handler_party_name',
        'transporter_plate_no',
        'weight_kg',
        'storage_bin_code',
        'prev_custody_hash',
        'current_custody_hash',
        'digital_signature',
        'event_at',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'event_at' => 'datetime',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(MedicalWasteManifest::class, 'manifest_id');
    }

    /**
     * Menghitung hash kriptografis rantai kustodi Festronik (Chain of Custody).
     */
    public static function computeChainHash(
        string $prevHash,
        int $manifestId,
        string $eventType,
        float $weightKg,
        string $handlerParty,
        string $timestampIso
    ): string {
        $payload = implode('|', [
            $prevHash,
            $manifestId,
            $eventType,
            number_format($weightKg, 3, '.', ''),
            $handlerParty,
            $timestampIso,
        ]);

        return hash('sha256', $payload);
    }
}
