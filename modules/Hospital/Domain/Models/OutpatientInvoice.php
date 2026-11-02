<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OutpatientInvoice extends Model
{
    protected $table = 'hsp_outpatient_invoices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'doctor_fee_idr' => 'integer',
            'medication_fee_idr' => 'integer',
            'total_amount_idr' => 'integer',
            'ina_cbg_tariff_idr' => 'integer',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(OutpatientEncounter::class, 'encounter_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OutpatientPayment::class, 'invoice_id');
    }

    public function bpjsClaim(): HasOne
    {
        return $this->hasOne(BpjsClaim::class, 'invoice_id');
    }
}
