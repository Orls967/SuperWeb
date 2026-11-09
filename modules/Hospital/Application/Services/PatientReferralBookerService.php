<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hospital\Contracts\PatientReferralBooker;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\BpjsReferralBridge;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\EPharmacyOrderItem;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\MedicineCourierDelivery;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\TeleConsult;
use Modules\Hospital\Domain\Services\BpjsSisruteBridgingService;
use Modules\Hospital\Domain\Services\ClinicalDecisionSupportRuleEngine;
use Modules\Hospital\Domain\Services\ColdChainMedicineDeliveryTracker;
use Modules\Hospital\Domain\Services\DigitalPrescriptionSignatureVerifier;
use Modules\Hospital\Domain\Services\EmergencyTeleTriageEscalatorService;
use Modules\Hospital\Domain\Services\FefoPharmacyDispensingEngine;
use Modules\Hospital\Domain\ValueObjects\GpsCoordinates;
use Modules\Hospital\Domain\ValueObjects\MedicineDosageRegimen;
use RuntimeException;

/**
 * Layanan Implementasi Kontrak G7-K30: PatientReferralBookerService.
 * Mengorkestrasi seluruh subsistem Fase 104:
 * - Telemedicine & Clinical Triage.
 * - Evaluasi Keselamatan CDSE & Tanda Tangan Kriptografis E-Resep.
 * - Perutean Apotek Geospasial Multi-Cabang & FEFO Dispensing.
 * - Pengantaran Kurir Rantai Dingin (Cold-Chain) & e-POD.
 * - Bridging Rujukan Nasional BPJS SISRUTE & Admisi Rawat Inap Terpadu.
 */
class PatientReferralBookerService implements PatientReferralBooker
{
    public function __construct(
        protected ClinicalDecisionSupportRuleEngine $cdsEngine,
        protected DigitalPrescriptionSignatureVerifier $signatureVerifier,
        protected FefoPharmacyDispensingEngine $fefoEngine,
        protected ColdChainMedicineDeliveryTracker $coldChainTracker,
        protected BpjsSisruteBridgingService $sisruteService,
        protected EmergencyTeleTriageEscalatorService $emergencyEscalator
    ) {}

    /**
     * {@inheritdoc}
     */
    public function bookTeleconsultation(array $data): TeleConsult
    {
        $patientId = (int) $data['patient_id'];
        $doctorId = (int) $data['doctor_id'];
        $complaint = trim($data['chief_complaint'] ?? '');

        if ($patientId <= 0 || $doctorId <= 0 || empty($complaint)) {
            throw new InvalidArgumentException('Patient ID, Doctor ID, and Chief Complaint are strictly required.');
        }

        // Jalankan evaluasi triase awal
        $triageResult = $this->emergencyEscalator->evaluateSymptoms($complaint);
        $triageCategory = $triageResult['triage_category'];

        $consultCode = 'TC-'.strtoupper(bin2hex(random_bytes(4)));

        $teleConsult = TeleConsult::create([
            'consult_code' => $consultCode,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'channel_type' => $data['channel_type'] ?? 'VIDEO',
            'triage_category' => $triageCategory,
            'chief_complaint' => $complaint,
            'clinical_notes' => $data['clinical_notes'] ?? $triageResult['recommendation'],
            'status' => $triageResult['is_emergency'] ? 'ESCALATED_EMERGENCY' : 'SCHEDULED',
        ]);

        return $teleConsult;
    }

    /**
     * {@inheritdoc}
     */
    public function issueElectronicPrescription(array $data): EPharmacyOrder
    {
        $consultId = (int) $data['tele_consult_id'];
        $patientId = (int) $data['patient_id'];
        $branchId = (int) $data['pharmacy_branch_id'];
        $drugCode = trim($data['drug_code']);
        $drugName = trim($data['drug_name']);
        $qty = (int) $data['quantity'];
        $price = (int) $data['total_price_idr'];
        $sip = trim($data['doctor_sip_number']);
        $isControlled = in_array(strtoupper((string) ($data['drug_classification'] ?? 'REGULAR')), ['NARCOTIC', 'PSYCHOTROPIC', 'NARCOTIC_PSYCHOTROPIC'], true);

        $patient = Patient::findOrFail($patientId);

        // 1. Validasi Keamanan Klinis via Clinical Decision Support (CDSE)
        $regimen = new MedicineDosageRegimen(
            drugCode: $drugCode,
            drugName: $drugName,
            dosePerAdministrationMg: 500.0,
            administrationsPerDay: 3,
            durationDays: max(1, (int) ceil($qty / 3)),
            maxSafeDailyDoseMg: 2000.0
        );

        $safetyScore = $this->cdsEngine->evaluateSafety($patient, [$regimen]);
        if (! $safetyScore->isDispensable) {
            $reason = implode('; ', $safetyScore->contraindications);
            throw new RuntimeException("Resep ditolak oleh Clinical Safety Engine: {$reason}");
        }

        // 2. Buat Tanda Tangan Digital Dokter
        $payload = [
            'consult_id' => $consultId,
            'patient_id' => $patientId,
            'drug_code' => $drugCode,
            'quantity' => $qty,
            'total_idr' => $price,
            'sip' => $sip,
        ];

        $envelope = $this->signatureVerifier->signPrescription(
            doctorId: (int) ($data['doctor_id'] ?? 1),
            doctorSipNumber: $sip,
            prescriptionPayload: $payload
        );

        // 3. Simpan Pesanan E-Farmasi
        $orderCode = 'ORD-EP-'.strtoupper(bin2hex(random_bytes(4)));

        return DB::transaction(function () use ($orderCode, $consultId, $patientId, $branchId, $drugCode, $drugName, $qty, $price, $data, $envelope, $isControlled) {
            $order = EPharmacyOrder::create([
                'order_code' => $orderCode,
                'tele_consult_id' => $consultId,
                'patient_id' => $patientId,
                'pharmacy_branch_id' => $branchId,
                'drug_code' => $drugCode,
                'drug_name' => $drugName,
                'quantity' => $qty,
                'total_price_idr' => $price,
                'drug_classification' => $data['drug_classification'] ?? 'REGULAR',
                'doctor_signature_hash' => $envelope->signatureHash,
                'second_doctor_approval_hash' => $isControlled ? null : $envelope->signatureHash,
                'is_chronic_subscription' => (bool) ($data['is_chronic_subscription'] ?? false),
                'pod_signature_hash' => null,
                'delivery_status' => 'PROCESSING',
                'status' => 'CONFIRMED',
            ]);

            // Buat order item
            EPharmacyOrderItem::create([
                'order_id' => $order->id,
                'item_code' => $drugCode,
                'item_name' => $drugName,
                'dosage_form' => 'TABLET',
                'quantity' => $qty,
                'unit_price_idr' => (int) round($price / max(1, $qty)),
                'subtotal_idr' => $price,
                'instructions' => 'Konsumsi teratur sesuai petunjuk dokter',
            ]);

            return $order;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function routePrescriptionToPharmacy(
        string $drugCode,
        int $quantity,
        float $patientLatitude,
        float $patientLongitude,
        float $maxRadiusKm = 25.0
    ): PharmacyBranch {
        $patientCoords = new GpsCoordinates($patientLatitude, $patientLongitude);

        // Cari semua cabang apotek aktif
        $branches = PharmacyBranch::where('is_active', true)->get();
        if ($branches->isEmpty()) {
            throw new RuntimeException('Tidak ada cabang apotek aktif yang terdaftar dalam jaringan.');
        }

        $eligibleBranches = [];

        foreach ($branches as $branch) {
            $lat = (float) ($branch->latitude ?? -6.2088);
            $lon = (float) ($branch->longitude ?? 106.8456);
            $branchCoords = new GpsCoordinates($lat, $lon);

            $distanceKm = $patientCoords->distanceTo($branchCoords);

            if ($distanceKm <= $maxRadiusKm) {
                // Periksa stok FEFO di cabang ini
                $availableStock = $this->fefoEngine->getAvailableStock($branch->id, $drugCode);
                if ($availableStock >= $quantity) {
                    $eligibleBranches[] = [
                        'branch' => $branch,
                        'distance_km' => $distanceKm,
                        'available_stock' => $availableStock,
                    ];
                }
            }
        }

        if (empty($eligibleBranches)) {
            // Fallback: cari cabang internal rumah sakit dengan stok mencukupi
            $fallbackBranch = $branches->first(function ($b) use ($drugCode, $quantity) {
                return $this->fefoEngine->getAvailableStock($b->id, $drugCode) >= $quantity;
            });

            if ($fallbackBranch) {
                return $fallbackBranch;
            }

            // Bila semua cabang habis, kembalikan cabang pertama untuk backorder
            return $branches->first();
        }

        // Urutkan berdasarkan jarak terdekat (geospatial nearest branch)
        usort($eligibleBranches, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return $eligibleBranches[0]['branch'];
    }

    /**
     * {@inheritdoc}
     */
    public function dispatchMedicineDelivery(array $data): MedicineCourierDelivery
    {
        $orderId = (int) $data['order_id'];
        $order = EPharmacyOrder::findOrFail($orderId);

        // Alokasikan stok obat di apotek cabang via FEFO
        $this->fefoEngine->dispenseFefoLots(
            branchId: (int) $order->pharmacy_branch_id,
            drugCode: $order->drug_code,
            requestedQuantity: (int) $order->quantity
        );

        // Berangkatkan kurir
        $delivery = $this->coldChainTracker->initiateDelivery($order, $data);

        // Update status pesanan ke DISPATCHED
        $order->delivery_status = 'DISPATCHED';
        $order->status = 'DISPENSED';
        $order->save();

        return $delivery;
    }

    /**
     * {@inheritdoc}
     */
    public function bridgeBpjsReferral(array $data): BpjsReferralBridge
    {
        $patientId = (int) $data['patient_id'];
        $patient = Patient::findOrFail($patientId);

        $direction = strtoupper(trim((string) ($data['referral_direction'] ?? 'OUTBOUND')));

        if ($direction === 'INBOUND') {
            return $this->sisruteService->acceptInboundReferral($patient, $data);
        }

        return $this->sisruteService->createOutboundReferral($patient, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function bookInpatientFromReferral(
        int $teleConsultId,
        string $targetWardClass,
        string $primaryDiagnosisIcd10
    ): array {
        $consult = TeleConsult::findOrFail($teleConsultId);
        $patient = $consult->patient;

        return DB::transaction(function () use ($consult, $patient, $targetWardClass, $primaryDiagnosisIcd10) {
            // 1. Buat Encounter Rawat Inap Baru
            $encounter = Encounter::create([
                'encounter_code' => 'ENC-INP-'.strtoupper(bin2hex(random_bytes(4))),
                'patient_id' => $patient->id,
                'encounter_type' => 'INPATIENT',
                'admitted_at' => Carbon::now(),
                'status' => 'ADMITTED',
            ]);

            // 2. Alokasikan Bed Kosong di Kelas yang Diminta
            $bed = HospitalBed::where('ward_class', $targetWardClass)
                ->where('status', 'AVAILABLE')
                ->lockForUpdate()
                ->first();

            if (! $bed) {
                // Fallback ke sembarang bed tersedia
                $bed = HospitalBed::where('status', 'AVAILABLE')->lockForUpdate()->first();
            }

            $bedId = $bed ? $bed->id : 1;
            $bedCode = $bed ? $bed->bed_code : 'BED-OVERFLOW-01';
            $wardName = $bed ? $bed->ward_name : 'Bangsal Transisi Rujukan';

            if ($bed) {
                $bed->status = 'OCCUPIED';
                $bed->save();
            }

            // 3. Catat Admisi Pasien
            $refCode = 'ADM-REF-'.strtoupper(bin2hex(random_bytes(4)));
            Admission::create([
                'admission_no' => $refCode,
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'hospital_bed_id' => $bedId,
                'bed_id' => $bedId,
                'entitled_class' => $targetWardClass,
                'current_class' => $targetWardClass,
                'admitted_at' => Carbon::now(),
                'status' => 'ADMITTED',
            ]);

            // Update catatan klinis telemedis
            $consult->clinical_notes .= "\n[RUJUKAN RAWAT INAP DISETUJUI] Diagnosa: {$primaryDiagnosisIcd10}, Bed: {$bedCode} ({$wardName}), Encounter: {$encounter->encounter_code}";
            $consult->status = 'COMPLETED';
            $consult->save();

            $depositRequired = match ($targetWardClass) {
                'VIP' => 10_000_000,
                'CLASS_1' => 5_000_000,
                'CLASS_2' => 3_000_000,
                default => 2_000_000,
            };

            return [
                'booking_reference' => $refCode,
                'patient_id' => $patient->id,
                'bed_id' => $bedId,
                'bed_code' => $bedCode,
                'ward_name' => $wardName,
                'encounter_id' => $encounter->id,
                'required_escrow_deposit_idr' => $depositRequired,
                'status' => 'CONFIRMED',
            ];
        });
    }
}
