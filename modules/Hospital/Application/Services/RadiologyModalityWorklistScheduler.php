<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Penjadwal Antrean Kerja Modalitas Radiologi (DICOM Modality Worklist / MWL SCP) (Fase 105).
 *
 * Mengelola integrasi SIMRS/HIS ke Modality Worklist (DICOM C-FIND Service Class Provider):
 * - Scheduled Procedure Step (SPS) Lifecycle: SCHEDULED, IN_PROGRESS, COMPLETED, DISCONTINUED
 * - Pemetaan DICOM Attributes:
 *   * Scheduled Station AE Title (0040,0001)
 *   * Scheduled Procedure Step Start Date/Time (0040,0002 / 0040,0003)
 *   * Modality (0008,0060) [CT, MR, CR, DX, US, NM, XA]
 *   * Scheduled Performing Physician's Name (0040,0006)
 *   * Requested Procedure ID (0040,1001) & Accession Number (0008,0050)
 *   * Study Instance UID (0020,000D)
 * - Penapisan query C-FIND berdasarkan AE Title, tanggal periksa, dan modalitas.
 */
class RadiologyModalityWorklistScheduler
{
    /**
     * Modalitas yang valid didukung dalam MWL.
     */
    public const VALID_MODALITIES = ['CT', 'MR', 'CR', 'DX', 'US', 'NM', 'XA', 'MG'];

    /**
     * Buat data entri Scheduled Procedure Step (SPS) baru untuk dicatatkan ke antrean MWL.
     *
     * @param  string  $accessionNumber  Nomor aksesi unik pemeriksaan
     * @param  string  $patientMrn  Nomor Rekam Medis (MRN)
     * @param  string  $patientName  Nama lengkap pasien
     * @param  string  $modality  Jenis modalitas (CT, MR, CR, DX, US)
     * @param  string  $stationAeTitle  Application Entity Title mesin modalitas
     * @param  string  $procedureDescription  Deskripsi tindakan (misal: 'CT Scan Thorax Kontras')
     * @param  Carbon  $scheduledDateTime  Waktu pelaksanaan yang dijadwalkan
     * @param  string|null  $physicianName  Nama dokter spesialis radiologi / DPJP
     * @param  bool  $contrastRequired  Apakah memerlukan zat kontras IV
     * @return array{
     *     sps_id: string,
     *     study_instance_uid: string,
     *     accession_number: string,
     *     patient_mrn: string,
     *     patient_name: string,
     *     modality: string,
     *     station_ae_title: string,
     *     procedure_description: string,
     *     scheduled_start_time: string,
     *     scheduled_physician: string,
     *     contrast_required: bool,
     *     sps_status: string,
     *     created_at: string
     * }
     */
    public function createScheduledProcedureStep(
        string $accessionNumber,
        string $patientMrn,
        string $patientName,
        string $modality,
        string $stationAeTitle,
        string $procedureDescription,
        Carbon $scheduledDateTime,
        ?string $physicianName = null,
        bool $contrastRequired = false
    ): array {
        $modalityKey = strtoupper(trim($modality));
        if (! in_array($modalityKey, self::VALID_MODALITIES, true)) {
            throw new InvalidArgumentException("Modalitas radiologi '{$modality}' tidak valid atau belum didukung.");
        }

        if (trim($stationAeTitle) === '') {
            throw new InvalidArgumentException('Station AE Title tidak boleh kosong.');
        }

        // Generate Study Instance UID standard OID format: 1.2.826.0.1.3680043.9.7134.[timestamp].[rand]
        $timestamp = now()->format('YmdHis');
        $randSuffix = random_int(10000, 99999);
        $studyInstanceUid = "1.2.826.0.1.3680043.9.7134.{$timestamp}.{$randSuffix}";

        $spsId = 'SPS-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        return [
            'sps_id' => $spsId,
            'study_instance_uid' => $studyInstanceUid,
            'accession_number' => $accessionNumber,
            'patient_mrn' => $patientMrn,
            'patient_name' => $patientName,
            'modality' => $modalityKey,
            'station_ae_title' => strtoupper($stationAeTitle),
            'procedure_description' => $procedureDescription,
            'scheduled_start_time' => $scheduledDateTime->toIso8601String(),
            'scheduled_physician' => $physicianName ?? 'dr. Sp.Rad On-Duty',
            'contrast_required' => $contrastRequired,
            'sps_status' => 'SCHEDULED',
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Simulasikan penanganan DICOM C-FIND Modality Worklist Query dari mesin modalitas.
     *
     * @param  list<array<string, mixed>>  $worklistQueue  Seluruh daftar item di antrean
     * @param array{
     *     station_ae_title?: string|null,
     *     modality?: string|null,
     *     date_from?: Carbon|null,
     *     date_to?: Carbon|null,
     *     patient_mrn?: string|null,
     *     accession_number?: string|null
     * } $queryFilters Filter parameter C-FIND
     * @return list<array<string, mixed>> Daftar prosedur yang cocok dengan query
     */
    public function handleCFindQuery(array $worklistQueue, array $queryFilters): array
    {
        $filtered = [];

        foreach ($worklistQueue as $item) {
            // Status harus SCHEDULED atau IN_PROGRESS
            if (! in_array($item['sps_status'] ?? '', ['SCHEDULED', 'IN_PROGRESS'], true)) {
                continue;
            }

            // Filter Station AE Title
            if (! empty($queryFilters['station_ae_title'])) {
                if (strtoupper((string) $queryFilters['station_ae_title']) !== strtoupper((string) ($item['station_ae_title'] ?? ''))) {
                    continue;
                }
            }

            // Filter Modality
            if (! empty($queryFilters['modality'])) {
                if (strtoupper((string) $queryFilters['modality']) !== strtoupper((string) ($item['modality'] ?? ''))) {
                    continue;
                }
            }

            // Filter Accession Number
            if (! empty($queryFilters['accession_number'])) {
                if ((string) $queryFilters['accession_number'] !== (string) ($item['accession_number'] ?? '')) {
                    continue;
                }
            }

            // Filter Patient MRN
            if (! empty($queryFilters['patient_mrn'])) {
                if ((string) $queryFilters['patient_mrn'] !== (string) ($item['patient_mrn'] ?? '')) {
                    continue;
                }
            }

            // Filter Date
            if (isset($queryFilters['date_from']) || isset($queryFilters['date_to'])) {
                $schedDate = Carbon::parse($item['scheduled_start_time']);

                if (isset($queryFilters['date_from']) && $schedDate->lessThan($queryFilters['date_from'])) {
                    continue;
                }
                if (isset($queryFilters['date_to']) && $schedDate->greaterThan($queryFilters['date_to'])) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Perbarui status alur kerja Scheduled Procedure Step (SPS).
     *
     * @param  array<string, mixed>  $spsItem  Data SPS saat ini
     * @param  string  $newStatus  Target status ('IN_PROGRESS', 'COMPLETED', 'DISCONTINUED')
     * @param  string|null  $discontinueReason  Alasan pembatalan (wajib jika DISCONTINUED)
     * @return array<string, mixed> Data SPS yang telah diperbarui
     */
    public function transitionSpsStatus(array $spsItem, string $newStatus, ?string $discontinueReason = null): array
    {
        $target = strtoupper($newStatus);
        $current = $spsItem['sps_status'] ?? 'SCHEDULED';

        $validTransitions = [
            'SCHEDULED' => ['IN_PROGRESS', 'DISCONTINUED'],
            'IN_PROGRESS' => ['COMPLETED', 'DISCONTINUED'],
            'COMPLETED' => [],
            'DISCONTINUED' => [],
        ];

        if (! isset($validTransitions[$current]) || ! in_array($target, $validTransitions[$current], true)) {
            throw new InvalidArgumentException("Transisi status SPS dari '{$current}' ke '{$target}' tidak diizinkan.");
        }

        if ($target === 'DISCONTINUED' && empty($discontinueReason)) {
            throw new InvalidArgumentException('Alasan pembatalan (discontinue reason) wajib dicantumkan.');
        }

        $spsItem['sps_status'] = $target;
        $spsItem['updated_at'] = now()->toIso8601String();

        if ($target === 'IN_PROGRESS') {
            $spsItem['acquisition_start_time'] = now()->toIso8601String();
        } elseif ($target === 'COMPLETED') {
            $spsItem['acquisition_end_time'] = now()->toIso8601String();
        } elseif ($target === 'DISCONTINUED') {
            $spsItem['discontinued_at'] = now()->toIso8601String();
            $spsItem['discontinue_reason'] = $discontinueReason;
        }

        return $spsItem;
    }
}
