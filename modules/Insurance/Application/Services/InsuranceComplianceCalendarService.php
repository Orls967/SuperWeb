<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;

final class InsuranceComplianceCalendarService
{
    /**
     * Jadwal regulasi standar asuransi OJK / APPARDI.
     *
     * @return array<int, array{code: string, title: string, regulator: string, periodicity: string, due_month: int, due_day: int, penalty_per_day_idr: int, description: string}>
     */
    public function getRegulatoryDeadlines(): array
    {
        return [
            [
                'code' => 'OJK-RBC-Q1',
                'title' => 'Laporan Tingkat Solvabilitas (RBC) Triwulan I',
                'regulator' => 'Otoritas Jasa Keuangan (OJK)',
                'periodicity' => 'QUARTERLY',
                'due_month' => 4,
                'due_day' => 30,
                'penalty_per_day_idr' => 100000000, // 1jt/hari
                'description' => 'Penyampaian rasio kecukupan modal berbasis risiko minimum 120% sesuai POJK No. 71/2016.',
            ],
            [
                'code' => 'OJK-RBC-Q2',
                'title' => 'Laporan Tingkat Solvabilitas (RBC) Triwulan II',
                'regulator' => 'Otoritas Jasa Keuangan (OJK)',
                'periodicity' => 'QUARTERLY',
                'due_month' => 7,
                'due_day' => 31,
                'penalty_per_day_idr' => 100000000,
                'description' => 'Penyampaian posisi keuangan tengah tahunan dan rasio cadangan teknis aktuaria.',
            ],
            [
                'code' => 'OJK-RBC-Q3',
                'title' => 'Laporan Tingkat Solvabilitas (RBC) Triwulan III',
                'regulator' => 'Otoritas Jasa Keuangan (OJK)',
                'periodicity' => 'QUARTERLY',
                'due_month' => 10,
                'due_day' => 31,
                'penalty_per_day_idr' => 100000000,
                'description' => 'Penyampaian rasio solvabilitas triwulan ketiga dan eksposur reasuransi luar negeri.',
            ],
            [
                'code' => 'OJK-ANNUAL-AUDIT',
                'title' => 'Laporan Keuangan & Aktuaria Tahunan Teraudit',
                'regulator' => 'Otoritas Jasa Keuangan (OJK)',
                'periodicity' => 'ANNUAL',
                'due_month' => 4,
                'due_day' => 30,
                'penalty_per_day_idr' => 500000000,
                'description' => 'Penyampaian laporan keuangan tahunan yang telah diaudit oleh KAP terdaftar OJK.',
            ],
            [
                'code' => 'APPARDI-COMMISSION-H1',
                'title' => 'Laporan Transaksi Pialang & Komisi Broker Semester I',
                'regulator' => 'APPARDI',
                'periodicity' => 'SEMI_ANNUAL',
                'due_month' => 7,
                'due_day' => 15,
                'penalty_per_day_idr' => 50000000,
                'description' => 'Rekonsiliasi komisi brokerage dan account current seluruh pialang asuransi terdaftar.',
            ],
            [
                'code' => 'DSN-MUI-SHARIA-CLEARANCE',
                'title' => 'Laporan Kepatuhan Syariah & Opini DPS Tahunan',
                'regulator' => 'Dewan Syariah Nasional (DSN-MUI)',
                'periodicity' => 'ANNUAL',
                'due_month' => 3,
                'due_day' => 31,
                'penalty_per_day_idr' => 100000000,
                'description' => 'Verifikasi kepatuhan akad Tabarru, Wakalah bil Ujrah, dan pembagian surplus underwriting.',
            ],
        ];
    }

    /**
     * Mengevaluasi status kepatuhan batas waktu pelaporan untuk tahun tertentu.
     *
     * @return array<string, mixed>
     */
    public function evaluateComplianceSchedule(int $year): array
    {
        $today = CarbonImmutable::now();
        $deadlines = $this->getRegulatoryDeadlines();

        $schedule = [];
        $overdueCount = 0;
        $upcomingCount = 0;
        $completedCount = 0;

        foreach ($deadlines as $d) {
            $dueDate = CarbonImmutable::create($year, $d['due_month'], $d['due_day'], 23, 59, 59);
            $isOverdue = $today->greaterThan($dueDate);
            $daysRemaining = $today->diffInDays($dueDate, false);

            if ($isOverdue) {
                $status = 'OVERDUE';
                $overdueCount++;
            } elseif ($daysRemaining <= 30) {
                $status = 'DUE_SOON';
                $upcomingCount++;
            } else {
                $status = 'SCHEDULED';
                $completedCount++;
            }

            $schedule[] = [
                'code' => $d['code'],
                'title' => $d['title'],
                'regulator' => $d['regulator'],
                'due_date' => $dueDate->toDateString(),
                'days_remaining' => (int) $daysRemaining,
                'status' => $status,
                'penalty_per_day_idr' => $d['penalty_per_day_idr'],
                'description' => $d['description'],
            ];
        }

        return [
            'calendar_year' => $year,
            'evaluated_at' => $today->toIso8601String(),
            'total_obligations' => count($deadlines),
            'overdue_count' => $overdueCount,
            'upcoming_count' => $upcomingCount,
            'scheduled_count' => $completedCount,
            'schedule' => $schedule,
        ];
    }
}
