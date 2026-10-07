<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Modules\Integration\Domain\Models\RegulatoryObligation;

/**
 * Regulatory Compliance Service (Fase 144.3)
 *
 * Handles:
 *  - Seeding compliance obligations for 17 lines
 *  - Checking expired/overdue obligations → blocks module operations
 *  - Escalation calendar generation
 *  - Audit: 0 overdue = HEALTHY
 */
class RegulatoryComplianceService
{
    /** Regulatory obligation definitions by line */
    private const OBLIGATIONS_TEMPLATE = [
        'HOS' => [
            ['code' => 'HOS-KMK-IZIN-RS', 'name' => 'UU No. 44/2009 – Izin Operasional Rumah Sakit', 'category' => 'LICENSE'],
            ['code' => 'HOS-PMK-EMR',      'name' => 'PMK No. 269/2008 – Rekam Medis Elektronik',     'category' => 'REPORTING'],
            ['code' => 'HOS-BPJS-CLAIM',   'name' => 'BPJS Kesehatan – Klaim Bulanan',               'category' => 'REPORTING'],
        ],
        'EGY' => [
            ['code' => 'EGY-ESDM-IUPTL',  'name' => 'UU No. 30/2009 – Izin Usaha Penyediaan Tenaga Listrik',     'category' => 'LICENSE'],
            ['code' => 'EGY-KWH-CERT',    'name' => 'SNI IEC 62052 – Sertifikasi Meteran KWh',                   'category' => 'CERTIFICATION'],
            ['code' => 'EGY-PLN-METER',   'name' => 'Permen ESDM No. 38/2013 – Laporan Meteran Bulanan ke PLN', 'category' => 'REPORTING'],
        ],
        'TLX' => [
            ['code' => 'TLX-KOMINFO-ISP', 'name' => 'PP No. 52/2000 – Izin Penyelenggaraan Jasa Telekomunikasi', 'category' => 'LICENSE'],
            ['code' => 'TLX-UU-PDP',     'name' => 'UU No. 27/2022 – Perlindungan Data Pribadi Telekomunikasi', 'category' => 'REPORTING'],
            ['code' => 'TLX-BTS-FREQ',   'name' => 'Kepmenhub No. 2/2001 – Frekuensi BTS Tahunan',             'category' => 'CERTIFICATION'],
        ],
        'MED' => [
            ['code' => 'MED-KPI-SIARAN',  'name' => 'UU No. 32/2002 – Izin Siaran Penyiaran',           'category' => 'LICENSE'],
            ['code' => 'MED-P3SPS',       'name' => 'KPI P3SPS – Standar Isi Siaran Kuartalan',         'category' => 'REPORTING'],
        ],
        'EDU' => [
            ['code' => 'EDU-DIKTI-AKKRED', 'name' => 'UU No. 12/2012 – Akreditasi Program Studi Dikti', 'category' => 'CERTIFICATION'],
            ['code' => 'EDU-LSP-CERT',   'name' => 'BNSP – Lisensi Lembaga Sertifikasi Profesi',       'category' => 'LICENSE'],
        ],
        'RET' => [
            ['code' => 'RET-UU-PKN',     'name' => 'UU No. 8/1999 – Perlindungan Konsumen Ritel',      'category' => 'REPORTING'],
            ['code' => 'RET-PDP-RITEL',  'name' => 'UU PDP No. 27/2022 – Data Konsumen Ritel',         'category' => 'REPORTING'],
        ],
        'MINE' => [
            ['code' => 'MINE-IUP',       'name' => 'UU No. 3/2020 Minerba – Izin Usaha Pertambangan',  'category' => 'LICENSE'],
            ['code' => 'MINE-AMDAL',     'name' => 'PP No. 22/2021 – AMDAL Pertambangan',              'category' => 'AUDIT'],
            ['code' => 'MINE-RKAB',      'name' => 'Permen ESDM No. 7/2020 – RKAB Tahunan',           'category' => 'REPORTING'],
        ],
        'HTL' => [
            ['code' => 'HTL-PARIWISATA', 'name' => 'UU No. 10/2009 – Izin Usaha Hotel & Pariwisata',  'category' => 'LICENSE'],
            ['code' => 'HTL-TANDA-DAFTAR', 'name', 'category' => 'REPORTING', 'name' => 'Permen Pariwisata No. 10/2018 – Tanda Daftar Usaha Pariwisata'],
        ],
        'VEN' => [
            ['code' => 'VEN-IMB-VENUE',  'name' => 'UU No. 28/2002 – Izin Mendirikan Bangunan Venue', 'category' => 'LICENSE'],
            ['code' => 'VEN-POLRI-MASSA', 'name' => 'UU No. 9/1998 – Izin Keramaian / Massa Venue',    'category' => 'LICENSE'],
        ],
        'INT' => [
            ['code' => 'INT-ISO27001',   'name' => 'ISO/IEC 27001:2022 – ISMS Certification',          'category' => 'CERTIFICATION'],
            ['code' => 'INT-PCI-DSS',    'name' => 'PCI DSS v4.0 – Payment Card Industry Compliance', 'category' => 'AUDIT'],
        ],
    ];

    /**
     * Seed compliance obligations for all 17 lines.
     * Idempotent: uses firstOrCreate.
     */
    public function seedObligations(): int
    {
        $seeded = 0;
        foreach (self::OBLIGATIONS_TEMPLATE as $lineCode => $obligations) {
            foreach ($obligations as $obl) {
                $dueDate = now()->addMonths(rand(1, 12))->toDateString();
                $existed = RegulatoryObligation::firstOrCreate(
                    ['obligation_code' => $obl['code']],
                    [
                        'line_code' => $lineCode,
                        'regulation_name' => $obl['name'],
                        'category' => $obl['category'],
                        'due_date' => $dueDate,
                        'status' => RegulatoryObligation::STATUS_PENDING,
                        'escalation_level' => 'MEDIUM',
                    ]
                );
                if ($existed->wasRecentlyCreated) {
                    $seeded++;
                }
            }
        }

        return $seeded;
    }

    /**
     * Mark an obligation as COMPLIANT.
     */
    public function markCompliant(string $obligationCode, ?string $notes = null): RegulatoryObligation
    {
        $obl = RegulatoryObligation::where('obligation_code', $obligationCode)->firstOrFail();
        $obl->update([
            'status' => RegulatoryObligation::STATUS_COMPLIANT,
            'last_checked_at' => now(),
            'notes' => $notes,
        ]);

        return $obl->fresh();
    }

    /**
     * Mark an obligation as BLOCKED (critical violation – blocks module operations).
     */
    public function blockObligation(string $obligationCode, string $reason): RegulatoryObligation
    {
        $obl = RegulatoryObligation::where('obligation_code', $obligationCode)->firstOrFail();
        $obl->update([
            'status' => RegulatoryObligation::STATUS_BLOCKED,
            'escalation_level' => 'CRITICAL',
            'last_checked_at' => now(),
            'notes' => $reason,
        ]);

        return $obl->fresh();
    }

    /**
     * Check whether a line can operate (no BLOCKED/CRITICAL overdue obligations).
     */
    public function canLineOperate(string $lineCode): bool
    {
        return ! RegulatoryObligation::where('line_code', strtoupper($lineCode))
            ->where(function ($q) {
                $q->where('status', RegulatoryObligation::STATUS_BLOCKED)
                    ->orWhere(function ($q2) {
                        $q2->where('due_date', '<', now()->toDateString())
                            ->where('status', '!=', RegulatoryObligation::STATUS_COMPLIANT)
                            ->where('escalation_level', 'CRITICAL');
                    });
            })
            ->exists();
    }

    /**
     * Generate compliance escalation calendar for the next N days.
     */
    public function getEscalationCalendar(int $daysAhead = 30): array
    {
        $cutoff = now()->addDays($daysAhead)->toDateString();

        return RegulatoryObligation::where('status', '!=', RegulatoryObligation::STATUS_COMPLIANT)
            ->where('due_date', '<=', $cutoff)
            ->orderBy('due_date')
            ->get()
            ->map(fn ($o) => [
                'line' => $o->line_code,
                'code' => $o->obligation_code,
                'regulation' => $o->regulation_name,
                'due_date' => $o->due_date->toDateString(),
                'status' => $o->status,
                'escalation_level' => $o->escalation_level,
                'days_until_due' => (int) now()->diffInDays($o->due_date, false),
            ])
            ->all();
    }

    /**
     * Full audit: returns status per line and overall discrepancy count.
     */
    public function audit(): array
    {
        $blocked = RegulatoryObligation::where('status', RegulatoryObligation::STATUS_BLOCKED)->count();
        $overdue = RegulatoryObligation::where('due_date', '<', now()->toDateString())
            ->where('status', '!=', RegulatoryObligation::STATUS_COMPLIANT)
            ->count();

        $lines = RegulatoryObligation::selectRaw('line_code, status, count(*) as cnt')
            ->groupBy('line_code', 'status')
            ->get()
            ->groupBy('line_code')
            ->map(fn ($rows) => $rows->pluck('cnt', 'status'))
            ->all();

        return [
            'status' => ($blocked === 0 && $overdue === 0) ? 'HEALTHY' : 'ATTENTION',
            'total_obligations' => RegulatoryObligation::count(),
            'blocked' => $blocked,
            'overdue' => $overdue,
            'discrepancy_count' => $blocked + $overdue,
            'lines_summary' => $lines,
        ];
    }
}
