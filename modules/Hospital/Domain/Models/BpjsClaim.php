<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsClaim extends Model
{
    protected $table = 'hsp_bpjs_claims';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ina_cbg_tariff_idr' => 'integer',
            'hospital_tariff_idr' => 'integer',
            'variance_amount_idr' => 'integer',
            'patient_copay_amount_idr' => 'integer',
            'adjudicated_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OutpatientInvoice::class, 'invoice_id');
    }
}
