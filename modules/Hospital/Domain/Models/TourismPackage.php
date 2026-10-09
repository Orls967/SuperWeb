<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourismPackage extends Model
{
    protected $table = 'hsp_tourism_packages';

    protected $fillable = [
        'package_code',
        'patient_id',
        'package_name',
        'total_price_idr',
        'hospital_share_idr',
        'hotel_share_idr',
        'transport_share_idr',
        'current_milestone',
        'status',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
