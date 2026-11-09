<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Carbon;
use Modules\Hospital\Domain\Models\DicomInstance;
use Modules\Hospital\Domain\Models\ImagingSeries;
use Modules\Hospital\Domain\Models\ImagingStudy;

/**
 * Layanan Ekstraksi Metadata DICOMweb WADO-RS & Zero-Footprint Viewer (Fase 105).
 */
class DicomWebMetadataExtractor
{
    /**
     * Menghasilkan payload JSON lengkap untuk Zero-Footprint Web Viewer.
     *
     * @return array<string, mixed>
     */
    public function extractStudyViewerMetadata(ImagingStudy $study): array
    {
        $patient = $study->patient;
        $seriesCollection = ImagingSeries::with('study')
            ->where('study_id', $study->id)
            ->get();

        $windowPresets = $this->getWindowPresetsForBodyPart($study->body_part, $study->modality);

        $seriesPayload = [];
        foreach ($seriesCollection as $series) {
            $instances = DicomInstance::where('series_id', $series->id)
                ->orderBy('instance_number', 'asc')
                ->get();

            $instancePayload = [];
            foreach ($instances as $inst) {
                $instancePayload[] = [
                    'sop_instance_uid' => $inst->sop_instance_uid,
                    'instance_number' => $inst->instance_number,
                    'rows' => $inst->rows,
                    'columns' => $inst->columns,
                    'window_center' => $inst->window_center,
                    'window_width' => $inst->window_width,
                    'pixel_spacing' => $inst->pixel_spacing,
                    'wado_uri' => "/hospital/radiology/dicom/instances/{$inst->id}/pixeldata",
                ];
            }

            $seriesPayload[] = [
                'series_instance_uid' => $series->series_instance_uid,
                'series_number' => $series->series_number ?? 1,
                'modality' => $series->modality,
                'series_description' => $series->series_description ?? "{$series->modality} {$study->body_part}",
                'slice_count' => $series->slice_count,
                'storage_uri' => $series->storage_uri,
                'instances' => $instancePayload,
            ];
        }

        return [
            'study_instance_uid' => $study->study_instance_uid,
            'accession_number' => $study->accession_number ?? 'ACC-'.$study->id,
            'patient_mrn' => $patient ? $patient->mrn : 'UNKNOWN',
            'patient_name' => $patient ? $patient->name : 'Unknown Patient',
            'modality' => $study->modality,
            'body_part' => $study->body_part,
            'performed_at' => $study->performed_at ? (is_string($study->performed_at) ? Carbon::parse($study->performed_at)->toIso8601String() : $study->performed_at->toIso8601String()) : null,
            'turnaround_minutes' => $study->turnaround_minutes,
            'radiologist_findings' => $study->radiologist_findings,
            'status' => $study->status,
            'window_presets' => $windowPresets,
            'series' => $seriesPayload,
        ];
    }

    /**
     * Mendapatkan preset Window Width & Center Hounsfield Unit (HU) berdasarkan modalitas & anatomi.
     *
     * @return list<array{name: string, window_center: float, window_width: float}>
     */
    public function getWindowPresetsForBodyPart(string $bodyPart, string $modality): array
    {
        $body = strtoupper($bodyPart);

        if (str_contains($body, 'HEAD') || str_contains($body, 'BRAIN')) {
            return [
                ['name' => 'Brain', 'window_center' => 40.0, 'window_width' => 80.0],
                ['name' => 'Subdural', 'window_center' => 100.0, 'window_width' => 300.0],
                ['name' => 'Bone', 'window_center' => 400.0, 'window_width' => 2000.0],
                ['name' => 'Stroke Infarct', 'window_center' => 32.0, 'window_width' => 32.0],
            ];
        }

        if (str_contains($body, 'CHEST') || str_contains($body, 'THORAX') || str_contains($body, 'LUNG')) {
            return [
                ['name' => 'Mediastinum', 'window_center' => 50.0, 'window_width' => 350.0],
                ['name' => 'Lung', 'window_center' => -600.0, 'window_width' => 1500.0],
                ['name' => 'Chest Bone', 'window_center' => 350.0, 'window_width' => 2000.0],
            ];
        }

        if (str_contains($body, 'ABDOMEN') || str_contains($body, 'PELVIS')) {
            return [
                ['name' => 'Abdomen Soft Tissue', 'window_center' => 40.0, 'window_width' => 400.0],
                ['name' => 'Liver Contrast', 'window_center' => 60.0, 'window_width' => 150.0],
                ['name' => 'Pelvic Bone', 'window_center' => 350.0, 'window_width' => 1800.0],
            ];
        }

        if (str_contains($body, 'SPINE')) {
            return [
                ['name' => 'Spine Soft Tissue', 'window_center' => 50.0, 'window_width' => 350.0],
                ['name' => 'Spine Bone', 'window_center' => 400.0, 'window_width' => 1800.0],
            ];
        }

        return [
            ['name' => 'Standard Diagnostic', 'window_center' => 40.0, 'window_width' => 400.0],
            ['name' => 'Bone Window', 'window_center' => 350.0, 'window_width' => 2000.0],
        ];
    }
}
