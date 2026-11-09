<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PatientPassportEvent;

/**
 * G7-D60: Layanan Kriptografis Paspor Kesehatan Pasien (Human Passport).
 * Memverifikasi dan mencatat rantai hash CanonicalHashChain.
 */
class PatientPassportService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Merekam peristiwa klinis baru ke dalam rantai paspor pasien.
     *
     * @param  array<string, mixed>  $payloadData
     */
    public function recordEvent(
        Patient $patient,
        string $eventType,
        array $payloadData,
        ?int $actorId = null
    ): PatientPassportEvent {
        // Ambil event terakhir pasien
        $lastEvent = PatientPassportEvent::where('patient_id', $patient->id)
            ->orderByDesc('sequence_no')
            ->first();

        $nextSeq = $lastEvent !== null ? $lastEvent->sequence_no + 1 : 1;
        $prevHash = $lastEvent !== null ? $lastEvent->current_hash : self::GENESIS_HASH;

        $payloadJson = json_encode($payloadData, JSON_THROW_ON_ERROR);
        $payloadHash = hash('sha256', $payloadJson);

        $currentHash = hash('sha256', "{$prevHash}:{$nextSeq}:{$eventType}:{$payloadHash}");
        $signature = hash_hmac('sha256', $currentHash, config('app.key') ?? 'superweb-secret');

        $event = PatientPassportEvent::create([
            'patient_id' => $patient->id,
            'sequence_no' => $nextSeq,
            'event_type' => $eventType,
            'encrypted_payload' => $payloadData,
            'payload_hash' => $payloadHash,
            'prev_hash' => $prevHash,
            'current_hash' => $currentHash,
            'signature' => $signature,
            'actor_id' => $actorId,
            'recorded_at' => now(),
        ]);

        $patient->passport_hash = $currentHash;
        $patient->save();

        return $event;
    }

    /**
     * Alias pelengkap untuk kompatibilitas dengan aksi domain.
     *
     * @param  array<string, mixed>  $eventPayload
     */
    public function appendEvent(
        int $patientId,
        string $eventType,
        array $eventPayload,
        ?int $actorId = null
    ): PatientPassportEvent {
        $patient = Patient::findOrFail($patientId);

        return $this->recordEvent($patient, $eventType, $eventPayload, $actorId);
    }

    /**
     * Memeriksa apakah terjadi perusakan integritas paspor pasien.
     */
    public function detectTampering(int $patientId): bool
    {
        $patient = Patient::findOrFail($patientId);

        return ! $this->verifyChain($patient);
    }

    /**
     * Mengambil seluruh linimasa kronologis peristiwa paspor pasien.
     *
     * @return list<array<string, mixed>>
     */
    public function getChronologicalTimeline(int $patientId): array
    {
        return PatientPassportEvent::where('patient_id', $patientId)
            ->orderBy('sequence_no', 'asc')
            ->get()
            ->map(fn ($e) => [
                'sequence_no' => $e->sequence_no,
                'event_type' => $e->event_type,
                'payload' => $e->encrypted_payload,
                'current_hash' => $e->current_hash,
                'recorded_at' => $e->recorded_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Memverifikasi keutuhan rantai hash paspor pasien dari genesis.
     */
    public function verifyChain(Patient $patient): bool
    {
        $events = PatientPassportEvent::where('patient_id', $patient->id)
            ->orderBy('sequence_no')
            ->get();

        if ($events->isEmpty()) {
            return true;
        }

        $expectedPrev = self::GENESIS_HASH;
        foreach ($events as $e) {
            if ($e->prev_hash !== $expectedPrev) {
                return false;
            }

            $payloadJson = json_encode($e->encrypted_payload, JSON_THROW_ON_ERROR);
            $calculatedPayloadHash = hash('sha256', $payloadJson);
            if (! hash_equals($e->payload_hash, $calculatedPayloadHash)) {
                return false;
            }

            $calculatedCurrent = hash('sha256', "{$e->prev_hash}:{$e->sequence_no}:{$e->event_type}:{$e->payload_hash}");
            if (! hash_equals($e->current_hash, $calculatedCurrent)) {
                return false;
            }

            $expectedPrev = $e->current_hash;
        }

        return hash_equals($patient->passport_hash, $expectedPrev);
    }
}
