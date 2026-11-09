<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalWasteManifest extends Model
{
    protected $table = 'hsp_medical_waste_manifests';

    protected $fillable = [
        'manifest_number',
        'waste_category',
        'weight_kg',
        'source_ward_code',
        'certified_vendor_party_id',
        'custody_hash',
        'destruction_certificate_hash',
        'status',
    ];

    protected $casts = [
        'weight_kg' => 'float',
    ];

    public function custodyLogs(): HasMany
    {
        return $this->hasMany(WasteCustodyLog::class, 'manifest_id')->orderBy('id');
    }
}
