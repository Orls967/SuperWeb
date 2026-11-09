<?php

declare(strict_types=1);

namespace App\Quality\Reporting;

use Modules\Platform\Domain\Models\PhaseMetricSnapshot;

/**
 * Generator Laporan Kualitas Kode Markdown Formal untuk lampiran PR dan Audit Fase.
 */
final class MarkdownQualityAuditReporter
{
    public function generateReport(PhaseMetricSnapshot $snapshot): string
    {
        $md = [];
        $md[] = "# Laporan Audit Kualitas Kode — Fase {$snapshot->phase_id}";
        $md[] = '';
        $md[] = '**Stempel Waktu:** '.($snapshot->created_at?->format('Y-m-d H:i:s T') ?? date('c'));
        $md[] = '**Status Kepatuhan:** '.strtoupper($snapshot->status);
        $md[] = "**Sertifikat SHA-256:** `{$snapshot->certificate_hash}`";
        $md[] = '';
        $md[] = '## 1. Ringkasan Kuantitatif';
        $md[] = '| Dimensi Evaluasi | Nilai Aktual | Batas Ambang Keras | Status |';
        $md[] = '|---|---|---|---|';
        $md[] = sprintf('| Logika Murni (B1 LOC) | %s | ≥ 7.500 LOC | %s |', number_format($snapshot->b1_loc), $snapshot->b1_loc >= 7500 ? '✅ LULUS' : '❌ KURANG');
        $md[] = sprintf('| Decision Density (A2) | %.4f | ≥ 0.0500 | %s |', $snapshot->decision_density, $snapshot->decision_density >= 0.05 ? '✅ LULUS' : '❌ KURANG');
        $md[] = sprintf('| Rasio Duplikasi (A1) | %.2f%% | ≤ 1.00%% | %s |', $snapshot->duplicate_ratio * 100, $snapshot->duplicate_ratio <= 0.01 ? '✅ LULUS' : '❌ LEBIH');
        $md[] = sprintf('| Kelas Yatim / Orphan (A3) | %d | 0 kelas | %s |', $snapshot->orphan_count, $snapshot->orphan_count === 0 ? '✅ BERSIH' : '❌ TERDETEKSI');
        $md[] = '';
        $md[] = '## 2. Rincian Pelanggaran Anti-Padding (T-10)';

        $breakdown = $snapshot->breakdown ?? [];
        $violations = $breakdown['violations'] ?? [];

        if (empty($violations)) {
            $md[] = '_Tidak ada pelanggaran terdeteksi. Kode bebas dari pola manipulasi M-2 s/d M-5._';
        } else {
            foreach ($violations as $v) {
                $md[] = "- ⚠️ {$v}";
            }
        }

        $md[] = '';
        $md[] = '## 3. Verifikasi Matematis';
        $md[] = 'Laporan ini diterbitkan secara deterministik oleh `quality:phase-metrics` dan dienkapsulasi dengan tanda tangan digital HMAC-SHA256.';

        return implode("\n", $md);
    }
}
