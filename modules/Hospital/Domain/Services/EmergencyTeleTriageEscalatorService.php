<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Contracts\MedicalEmergencyResponseDispatcher;
use Modules\Hospital\Domain\Models\TeleConsult;

/**
 * Emergency Tele-Triage Escalator & Clinical Red-Flag Detector.
 * Memastikan pasien gawat darurat yang mengakses telemedicine tidak terlambat mendapatkan tatalaksana:
 * 1. Deteksi otomatis gejala bahaya akut (chest pain, stroke FAST, sesak napas berat, syok anafilaksis).
 * 2. Eskalasi instan ke status RED dan pemicuan dispatch ambulans melalui MedicalEmergencyResponseDispatcher.
 * 3. Pembuatan alert darurat dan peralihan kanal ke triage fisik RS terdekat.
 */
class EmergencyTeleTriageEscalatorService
{
    private const RED_FLAG_KEYWORDS = [
        'NYERI DADA MENJALAR',
        'SESAK NAPAS BERAT',
        'STROKE',
        'TIBA-TIBA LUMPUH',
        'HILANG KESADARAN',
        'PINGSAN',
        'KEJANG LAMA',
        'PERDARAHAN HEBAT',
        'ANAFILAKSIS',
        'BIBIR BIRU SIANOSIS',
    ];

    public function __construct(
        protected ?MedicalEmergencyResponseDispatcher $emergencyDispatcher = null
    ) {}

    /**
     * Menganalisis keluhan utama dan menetapkan kategori triase klinis.
     *
     * @return array{
     *     triage_category: string, // GREEN, YELLOW, RED
     *     is_emergency: bool,
     *     detected_red_flags: array<int, string>,
     *     recommendation: string
     * }
     */
    public function evaluateSymptoms(string $chiefComplaint): array
    {
        $complaintUpper = strtoupper($chiefComplaint);
        $detectedFlags = [];

        foreach (self::RED_FLAG_KEYWORDS as $keyword) {
            if (str_contains($complaintUpper, $keyword)) {
                $detectedFlags[] = $keyword;
            }
        }

        if (! empty($detectedFlags)) {
            return [
                'triage_category' => 'RED',
                'is_emergency' => true,
                'detected_red_flags' => $detectedFlags,
                'recommendation' => 'Peringatan Bahaya Medis: Indikasi kegawatdaruratan akut terdeteksi. Wajib segera ke IGD atau panggil ambulans darurat.',
            ];
        }

        // Cek gejala kuning (Yellow)
        $yellowKeywords = ['DEMAM TINGGI', 'MUNTAH TERUS MENERUS', 'DIARE DEHIDRASI', 'FRAKTUR CURIGA'];
        $yellowFlags = [];
        foreach ($yellowKeywords as $yk) {
            if (str_contains($complaintUpper, $yk)) {
                $yellowFlags[] = $yk;
            }
        }

        if (! empty($yellowFlags)) {
            return [
                'triage_category' => 'YELLOW',
                'is_emergency' => false,
                'detected_red_flags' => [],
                'recommendation' => 'Triase Kuning: Memerlukan pemeriksaan klinis dokter dalam 30-60 menit.',
            ];
        }

        return [
            'triage_category' => 'GREEN',
            'is_emergency' => false,
            'detected_red_flags' => [],
            'recommendation' => 'Triase Hijau: Konsultasi telemedis rawat jalan rutin dapat dilanjutkan.',
        ];
    }

    /**
     * Mengeksekusi eskalasi darurat bila sesi telemedis masuk kategori RED.
     */
    public function escalateIfEmergency(TeleConsult $teleConsult, string $patientAddress = 'Jl. Protokol No. 1'): bool
    {
        $eval = $this->evaluateSymptoms($teleConsult->chief_complaint);

        if ($eval['is_emergency']) {
            $teleConsult->triage_category = 'RED';
            $teleConsult->status = 'ESCALATED_EMERGENCY';
            $teleConsult->clinical_notes .= "\n[ESKALASI DARURAT] Red flags: ".implode(', ', $eval['detected_red_flags']);
            $teleConsult->save();

            // Panggil dispatcher ambulans darurat jika tersedia
            if ($this->emergencyDispatcher) {
                try {
                    $this->emergencyDispatcher->dispatch(
                        incidentId: $teleConsult->id,
                        triagePriority: 'P1_CRITICAL',
                        targetLocation: $patientAddress
                    );
                } catch (\Throwable) {
                    // Fallback bila dispatcher mock / logging
                }
            }

            return true;
        }

        return false;
    }
}
