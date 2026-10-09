<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Models\Contract;

/**
 * Laporan paparan kontrak + audit jadwal/ledger (29.8).
 *
 * Pajak, bea, arbitrase, dan penilaian risiko merupakan simulasi.
 */
class ContractReportService
{
    /**
     * Nilai kontrak aktif per tipe & pihak.
     *
     * @return array{by_type: array<int, object>, by_party: array<int, object>}
     */
    public function exposure(): array
    {
        $activeStatuses = [
            ContractStatus::Active->value,
            ContractStatus::Signed->value,
            ContractStatus::Suspended->value,
        ];

        $byType = Contract::query()
            ->whereIn('status', $activeStatuses)
            ->selectRaw('contract_type, COUNT(*) as contract_count, SUM(total_value_idr) as total_value_idr, SUM(used_value_idr) as used_value_idr')
            ->groupBy('contract_type')
            ->orderBy('contract_type')
            ->get();

        $byParty = DB::table('ctr_contract_parties')
            ->join('ctr_contracts', 'ctr_contracts.id', '=', 'ctr_contract_parties.contract_id')
            ->join('pty_parties', 'pty_parties.id', '=', 'ctr_contract_parties.party_id')
            ->whereIn('ctr_contracts.status', $activeStatuses)
            ->selectRaw('pty_parties.id as party_id, pty_parties.name as party_name, COUNT(DISTINCT ctr_contracts.id) as contract_count, SUM(ctr_contracts.total_value_idr) as total_value_idr, SUM(ctr_contracts.used_value_idr) as used_value_idr')
            ->groupBy('pty_parties.id', 'pty_parties.name')
            ->orderByDesc('total_value_idr')
            ->get();

        return ['by_type' => $byType->all(), 'by_party' => $byParty->all()];
    }

    /**
     * Aging kewajiban milestone (current, 1–30, 31–60, >60 hari lewat).
     *
     * @return array<string, array{count: int, amount_idr: int}>
     */
    public function obligationAging(): array
    {
        $rows = DB::table('ctr_milestones')
            ->whereIn('status', ['pending', 'in_progress', 'overdue'])
            ->selectRaw("CASE WHEN due_date >= date('now') THEN 'current' WHEN due_date >= date('now', '-30 days') THEN '1_30' WHEN due_date >= date('now', '-60 days') THEN '31_60' ELSE 'over_60' END as bucket, COUNT(*) as item_count, SUM(amount_idr) as amount_idr")
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $result = [];
        foreach (['current', '1_30', '31_60', 'over_60'] as $bucket) {
            $row = $rows->get($bucket);
            $result[$bucket] = [
                'count' => (int) ($row->item_count ?? 0),
                'amount_idr' => (int) ($row->amount_idr ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Audit integritas subledger finansial kontrak.
     *
     * Cek: `used_value_idr` cache == SUM(ctr_usage_ledger.amount_idr),
     * dan total jadwal termin tidak melebihi plafon kontrak (kecuali kontrak
     * tanpa plafon). Pajak tidak dihitung (simulasi).
     *
     * @return array{checked: int, discrepancies: array<int, array{contract_id: string, kind: string, ledger: int, cached: int, difference: int}>, balanced: bool}
     */
    public function audit(): array
    {
        $contracts = Contract::query()
            ->whereIn('status', [ContractStatus::Active, ContractStatus::Signed, ContractStatus::Suspended, ContractStatus::Draft])
            ->get(['id', 'total_value_idr', 'used_value_idr']);

        $usageByContract = DB::table('ctr_usage_ledger')
            ->selectRaw('contract_id, SUM(amount_idr) as total')
            ->groupBy('contract_id')
            ->pluck('total', 'contract_id');

        $scheduleByContract = DB::table('ctr_payment_schedules')
            ->selectRaw('contract_id, SUM(amount_idr) as total')
            ->groupBy('contract_id')
            ->pluck('total', 'contract_id');

        $discrepancies = [];

        foreach ($contracts as $contract) {
            $ledger = (int) ($usageByContract[$contract->id] ?? 0);
            $cached = (int) $contract->used_value_idr;

            if ($ledger !== $cached) {
                $discrepancies[] = [
                    'contract_id' => $contract->id,
                    'kind' => 'usage_cache',
                    'ledger' => $ledger,
                    'cached' => $cached,
                    'difference' => $ledger - $cached,
                ];
            }

            $scheduled = (int) ($scheduleByContract[$contract->id] ?? 0);
            if ($contract->total_value_idr > 0 && $scheduled > $contract->total_value_idr) {
                $discrepancies[] = [
                    'contract_id' => $contract->id,
                    'kind' => 'payment_schedule_over_contract_value',
                    'ledger' => $scheduled,
                    'cached' => (int) $contract->total_value_idr,
                    'difference' => $scheduled - (int) $contract->total_value_idr,
                ];
            }
        }

        return [
            'checked' => $contracts->count(),
            'discrepancies' => $discrepancies,
            'balanced' => $discrepancies === [],
        ];
    }

    /**
     * Summary kontrak yang akan kedaluwarsa dalam $days hari.
     *
     * @return array<int, Contract>
     */
    public function expiringSoon(int $days = 90): array
    {
        return Contract::query()
            ->with(['legalEntity', 'parties.party'])
            ->whereIn('status', [ContractStatus::Active->value, ContractStatus::Suspended->value])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('end_date')
            ->get()
            ->all();
    }
}
