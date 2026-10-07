<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EPharmacyOrder extends Model
{
    protected $table = 'hsp_epharmacy_orders';

    protected $fillable = [
        'order_code',
        'tele_consult_id',
        'patient_id',
        'pharmacy_branch_id',
        'drug_code',
        'drug_name',
        'quantity',
        'total_price_idr',
        'drug_classification',
        'doctor_signature_hash',
        'second_doctor_approval_hash',
        'is_chronic_subscription',
        'pod_signature_hash',
        'delivery_status',
        'status',
    ];

    protected $casts = [
        'is_chronic_subscription' => 'boolean',
    ];

    public function teleConsult(): BelongsTo
    {
        return $this->belongsTo(TeleConsult::class, 'tele_consult_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranch::class, 'pharmacy_branch_id');
    }
}
