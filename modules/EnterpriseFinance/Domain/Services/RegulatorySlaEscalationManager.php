<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Psr\Clock\ClockInterface;

/**
 * Manajer Eskalasi SLA Kepatuhan Regulasi Korporat (54.6).
 * Memantau batas waktu penyampaian laporan wajib ke otoritas (DJP, OJK, BEI, Kemendag)
 * dan memicu eskalasi bertingkat ke manajemen:
 * - Level 1 (T-30 hari): Notifikasi awal PIC operasional.
 * - Level 2 (T-14 hari): Peringatan Kepala Bagian Akuntansi & Pajak.
 * - Level 3 (T-7 hari): Eskalasi ke Direktur Keuangan (CFO).
 * - Level 4 (T-1 hari / Overdue): Peringatan Darurat Dewan Komisaris & Komite Audit.
 */
class RegulatorySlaEscalationManager
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Memindai seluruh tenggat waktu regulasi dan menentukan level eskalasi.
     *
     * @return array{
     *     total_monitored: int,
     *     level_1_notices: int,
     *     level_2_warnings: int,
     *     level_3_escalations: int,
     *     level_4_emergencies: int,
     *     items: list<array{item_code: string, title: string, regulator: string, due_date: string, days_remaining: int, escalation_level: string, target_role: string}>
     * }
     */
    public function scanEscalations(): array
    {
        $today = $this->now()->startOfDay();
        $deadlines = ComplianceDeadline::where('status', '!=', 'completed')->get();

        $l1 = 0;
        $l2 = 0;
        $l3 = 0;
        $l4 = 0;
        $items = [];

        foreach ($deadlines as $d) {
            $dueDate = CarbonImmutable::parse($d->due_date)->startOfDay();
            $daysRemaining = (int) $today->diffInDays($dueDate, false);

            if ($daysRemaining < 0) {
                $level = 'LEVEL_4_OVERDUE_EMERGENCY';
                $role = 'board_of_directors,audit_committee';
                $l4++;
            } elseif ($daysRemaining <= 1) {
                $level = 'LEVEL_4_URGENT_FINAL_DAY';
                $role = 'cfo,head_of_tax';
                $l4++;
            } elseif ($daysRemaining <= 7) {
                $level = 'LEVEL_3_DIRECTOR_ESCALATION';
                $role = 'cfo,accounting_manager';
                $l3++;
            } elseif ($daysRemaining <= 14) {
                $level = 'LEVEL_2_MANAGER_WARNING';
                $role = 'accounting_manager,tax_supervisor';
                $l2++;
            } else {
                $level = 'LEVEL_1_OPERATIONAL_NOTICE';
                $role = $d->assigned_role ?? 'operator';
                $l1++;
            }

            $items[] = [
                'item_code' => $d->item_code,
                'title' => $d->title,
                'regulator' => $d->regulatory_body,
                'due_date' => $d->due_date,
                'days_remaining' => $daysRemaining,
                'escalation_level' => $level,
                'target_role' => $role,
            ];
        }

        return [
            'total_monitored' => $deadlines->count(),
            'level_1_notices' => $l1,
            'level_2_warnings' => $l2,
            'level_3_escalations' => $l3,
            'level_4_emergencies' => $l4,
            'items' => $items,
        ];
    }
}
