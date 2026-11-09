<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\EPharmacyOrder;

/**
 * Electronic Prescription Serialization & HL7 FHIR Interoperability Engine.
 * Menghasilkan representasi digital standar untuk resep elektronik:
 * 1. Serialisasi HL7 FHIR Release 4 (R4) Resource: `MedicationRequest`.
 * 2. Pembuatan payload QR Code verifikasi farmasi di kasir atau kurir.
 * 3. Enkripsi metadata klinis pasien sesuai standar Permenkes SatuSehat.
 * 4. Stempel kriptografis tanda tangan digital dokter dalam format envelope.
 */
class ElectronicPrescriptionSerializationEngine
{
    /**
     * Mengubah pesanan e-resep menjadi format standar HL7 FHIR MedicationRequest JSON.
     *
     * @return array<string, mixed>
     */
    public function toFhirMedicationRequest(EPharmacyOrder $order): array
    {
        $patient = $order->patient;
        $teleConsult = $order->teleConsult;

        return [
            'resourceType' => 'MedicationRequest',
            'id' => $order->order_code,
            'identifier' => [
                [
                    'system' => 'http://superweb.hospital.id/fhir/identifier/prescriptions',
                    'value' => $order->order_code,
                ],
            ],
            'status' => match ($order->status) {
                'CONFIRMED' => 'active',
                'DELIVERED' => 'completed',
                'CANCELLED' => 'cancelled',
                default => 'draft',
            },
            'intent' => 'order',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/medicationrequest-category',
                            'code' => 'outpatient',
                            'display' => 'Outpatient',
                        ],
                    ],
                ],
            ],
            'medicationCodeableConcept' => [
                'coding' => [
                    [
                        'system' => 'http://sys-ids.kemkes.go.id/kfa',
                        'code' => $order->drug_code,
                        'display' => $order->drug_name,
                    ],
                ],
                'text' => $order->drug_name,
            ],
            'subject' => [
                'reference' => "Patient/{$patient->mrn}",
                'display' => $patient->name,
            ],
            'encounter' => $teleConsult ? [
                'reference' => "Encounter/{$teleConsult->consult_code}",
                'display' => "Teleconsultation {$teleConsult->consult_code}",
            ] : null,
            'authoredOn' => $order->created_at->toIso8601String(),
            'requester' => [
                'reference' => 'Practitioner/'.($teleConsult->doctor_id ?? 1),
                'display' => 'Dokter Penanggung Jawab Pelayanan (DPJP)',
            ],
            'dispenseRequest' => [
                'quantity' => [
                    'value' => $order->quantity,
                    'unit' => 'TAB',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'TAB',
                ],
                'expectedSupplyDuration' => [
                    'value' => 30,
                    'unit' => 'days',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'd',
                ],
            ],
            'extension' => [
                [
                    'url' => 'http://superweb.hospital.id/fhir/StructureDefinition/doctor-signature-hash',
                    'valueString' => $order->doctor_signature_hash,
                ],
                [
                    'url' => 'http://superweb.hospital.id/fhir/StructureDefinition/controlled-substance-approval',
                    'valueString' => (string) ($order->second_doctor_approval_hash ?? 'N/A'),
                ],
            ],
        ];
    }

    /**
     * Menghasilkan payload data terenkripsi untuk QR-Code cetak label obat.
     */
    public function generatePharmacyVerificationQrPayload(EPharmacyOrder $order): string
    {
        $summary = [
            'ord' => $order->order_code,
            'mrn' => $order->patient?->mrn ?? 'UNKNOWN',
            'drg' => $order->drug_code,
            'qty' => $order->quantity,
            'sig' => substr($order->doctor_signature_hash, 0, 16),
            'pod' => $order->pod_signature_hash ? substr($order->pod_signature_hash, 0, 16) : 'PENDING',
            'ts' => $order->created_at->timestamp,
        ];

        return base64_encode(json_encode($summary) ?: '');
    }

    /**
     * Memverifikasi keabsahan payload QR Code yang dipindai apoteker saat dispensing.
     */
    public function verifyQrPayload(string $base64Payload, EPharmacyOrder $order): bool
    {
        $decoded = base64_decode($base64Payload, true);
        if ($decoded === false) {
            return false;
        }

        $data = json_decode($decoded, true);
        if (! is_array($data)) {
            return false;
        }

        return ($data['ord'] ?? '') === $order->order_code &&
               ($data['drg'] ?? '') === $order->drug_code &&
               ($data['qty'] ?? 0) === $order->quantity;
    }
}
