<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalWasteManifest extends Model
{
    protected $table = 'hsp_medical_waste_manifests';

    protected $fillable = [
        'manifest_number',
        'waste_category',
        'weight_kg',
        'certified_vendor_party_id',
        'custody_hash',
        'destruction_certificate_hash',
        'status',
    ];
}
