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

    public static function getOrCreateArchFixture(): array
    {
        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_UMUM'], ['name' => 'Poli Umum', 'is_active' => true]);
        $doctorUser = \App\Models\User::firstOrCreate(['email' => 'doc_arch@hospital.local'], [
            'name' => 'Dr. Arch',
            'password' => bcrypt('secret'),
            'role' => 'doctor',
        ]);
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-ARCH-01'], [
            'user_id' => $doctorUser->id,
            'polyclinic_id' => $poli->id,
            'is_active' => true,
        ]);
        $queue = QueueTicket::firstOrCreate(['queue_code' => 'T-ARCH-01'], [
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'queue_date' => now()->toDateString(),
            'queue_number' => 1,
            'status' => 'WAITING',
        ]);
        $patient = Patient::firstOrCreate(['mrn' => 'MRN-ARCH-01'], [
            'name' => 'Pasien Arch',
            'date_of_birth' => '1990-01-01',
            'blood_type' => 'O',
            'passport_hash' => hash('sha256', 'p'),
        ]);
        $enc = self::firstOrCreate(['encounter_code' => 'ENC-ARCH-01'], [
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'CASH',
            'status' => 'CALLED',
        ]);
        $rx = OutpatientPrescription::firstOrCreate(['prescription_code' => 'RX-ARCH-01'], [
            'encounter_id' => $enc->id,
            'doctor_id' => $doctor->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'quantity' => 10,
            'status' => 'PRESCRIBED',
        ]);
        $inv = OutpatientInvoice::firstOrCreate(['invoice_code' => 'INV-ARCH-01'], [
            'encounter_id' => $enc->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 150000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);
        $claim = BpjsClaim::firstOrCreate(['claim_code' => 'CLM-ARCH-01'], [
            'invoice_id' => $inv->id,
            'ina_cbg_code' => 'Q-5-44-0',
            'ina_cbg_tariff_idr' => 180000,
            'hospital_tariff_idr' => 150000,
            'variance_amount_idr' => 30000,
            'status' => 'SUBMITTED',
        ]);

        return [
            'queue' => $queue,
            'encounter' => $enc,
            'prescription' => $rx,
            'invoice' => $inv,
            'claim' => $claim,
            'patient' => $patient,
        ];
    }
}
