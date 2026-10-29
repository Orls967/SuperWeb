<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OutpatientEncounter extends Model
{
    protected $table = 'hsp_outpatient_encounters';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'systolic_bp' => 'integer',
            'diastolic_bp' => 'integer',
            'pulse_rate' => 'integer',
            'respiratory_rate' => 'integer',
            'encrypted_doctor_notes' => 'encrypted',
            'encrypted_icd10_code' => 'encrypted',
            'examined_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_id');
    }

    public function polyclinic(): BelongsTo
    {
        return $this->belongsTo(Polyclinic::class, 'polyclinic_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(OutpatientPrescription::class, 'encounter_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(OutpatientInvoice::class, 'encounter_id');
    }

    public function cancel(string $reason = ''): void
    {
        if ($this->status !== 'REGISTERED') {
            throw new DomainException('Tidak dapat membatalkan kunjungan yang sudah dipanggil atau diperiksa.');
        }

        $this->update(['status' => 'CANCELLED']);
        if ($this->queue) {
            $this->queue->update(['status' => 'CANCELLED']);
        }
    }
}
