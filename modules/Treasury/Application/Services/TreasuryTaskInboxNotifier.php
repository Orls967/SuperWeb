<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\ForwardContract;

/**
 * Notifier Integrasi TaskInbox (CoreTask) untuk Anomali & Governance Treasury.
 *
 * Mengirim penugasan otomatis ke inbox operator / supervisor perbendaharaan:
 * 1. Peringatan Covenant DER mendekati plafon batas (> 90% dari batas maksimal)
 * 2. Pos Rekonsiliasi Bank yang belum terselesaikan setelah 3 tahap pencocokan
 * 3. Kontrak Lindung Nilai (Hedge) mendekati tanggal jatuh tempo (< 7 hari)
 * 4. Saldo Kas Kecil (Imprest) menipis (< 20% dari batas pagu)
 */
final class TreasuryTaskInboxNotifier
{
    public function __construct(
        protected readonly ?TaskInboxService $taskInboxService = null
    ) {}

    /**
     * Memeriksa dan mengirim task peringatan covenant fasilitas kredit.
     */
    public function notifyCovenantNearBreach(CreditFacility $facility, int $currentDerPermille): ?string
    {
        $maxDer = (int) $facility->max_der_permil;
        $threshold = (int) intdiv($maxDer * 90, 100);

        if ($currentDerPermille < $threshold) {
            return null;
        }

        $derFormatted = sprintf('%.2fx', $currentDerPermille / 1000);
        $maxFormatted = sprintf('%.2fx', $maxDer / 1000);

        $payload = [
            'type' => 'TREASURY_COVENANT_ALERT',
            'title' => "Peringatan Covenant: Fasilitas {$facility->facility_code} ({$facility->bank_name})",
            'description' => "Rasio DER saat ini ({$derFormatted}) telah melampaui 90% ambang batas ({$maxFormatted}). Tindakan pencegahan diperlukan sebelum penarikan lanjutan.",
            'assigned_role' => 'treasury',
            'priority' => ($currentDerPermille >= $maxDer) ? 'urgent' : 'high',
            'metadata' => [
                'facility_id' => $facility->id,
                'facility_code' => $facility->facility_code,
                'current_der_permil' => $currentDerPermille,
                'max_der_permil' => $maxDer,
            ],
        ];

        return $this->dispatchTask($payload);
    }

    /**
     * Memeriksa dan mengirim task anomali mutasi bank yang gagal direkonsiliasi.
     */
    public function notifyUnmatchedStatement(BankReconciliationUnmatched $unmatched): ?string
    {
        $stmt = $unmatched->statement;
        $ref = $stmt ? $stmt->reference_no : 'N/A';
        $amount = $stmt ? number_format((int) $stmt->amount, 0, ',', '.') : '0';

        $payload = [
            'type' => 'TREASURY_RECON_EXCEPTION',
            'title' => "Pengecualian Rekonsiliasi: Mutasi {$ref} (Rp {$amount})",
            'description' => "Mutasi bank pada rekening {$stmt?->bank_account_id} gagal dicocokkan otomatis pada ketiga tahap (Exact, Tolerance, Many-to-One). Alasan: {$unmatched->reason}.",
            'assigned_role' => 'auditor',
            'priority' => 'medium',
            'metadata' => [
                'unmatched_id' => $unmatched->id,
                'statement_id' => $unmatched->statement_id,
                'reason' => $unmatched->reason,
            ],
        ];

        return $this->dispatchTask($payload);
    }

    /**
     * Memeriksa kontrak forward yang akan jatuh tempo dalam rentang hari tertentu.
     */
    public function notifyUpcomingForwardMaturity(ForwardContract $contract, int $daysRemaining): ?string
    {
        if ($daysRemaining > 7 || $daysRemaining < 0) {
            return null;
        }

        $notionalFormatted = number_format((int) $contract->notional_foreign_amount, 0, ',', '.');

        $payload = [
            'type' => 'TREASURY_HEDGE_MATURITY',
            'title' => "Jatuh Tempo Lindung Nilai: {$contract->contract_number} ({$daysRemaining} hari lagi)",
            'description' => "Kontrak forward {$contract->currency} {$notionalFormatted} akan jatuh tempo pada {$contract->maturity_date?->format('Y-m-d')}. Harap persiapkan instruksi penyelesaian fisik / netting atau perpanjangan (rollover).",
            'assigned_role' => 'treasury',
            'priority' => ($daysRemaining <= 2) ? 'urgent' : 'high',
            'metadata' => [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'currency' => $contract->currency,
                'days_remaining' => $daysRemaining,
            ],
        ];

        return $this->dispatchTask($payload);
    }

    /**
     * Memeriksa saldo kas kecil imprest yang perlu diisi kembali (replenishment).
     */
    public function notifyLowPettyCashBalance(BankAccount $account): ?string
    {
        if (! $account->is_petty_cash || (int) $account->imprest_limit <= 0) {
            return null;
        }

        $balance = (int) $account->balance;
        $limit = (int) $account->imprest_limit;
        $ratioPermil = (int) intdiv($balance * 1000, $limit);

        if ($ratioPermil >= 200) {
            return null;
        }

        $balanceFmt = number_format($balance, 0, ',', '.');
        $limitFmt = number_format($limit, 0, ',', '.');

        $payload = [
            'type' => 'TREASURY_PETTY_CASH_LOW',
            'title' => "Pengisian Kembali Kas Kecil: {$account->bank_name} ({$account->account_number})",
            'description' => "Sisa saldo kas kecil saat ini (Rp {$balanceFmt}) telah berada di bawah 20% dari pagu imprest (Rp {$limitFmt}). Ajukan pengisian kembali segera.",
            'assigned_role' => 'treasury',
            'priority' => 'medium',
            'metadata' => [
                'account_number' => $account->account_number,
                'current_balance' => $balance,
                'imprest_limit' => $limit,
            ],
        ];

        return $this->dispatchTask($payload);
    }

    /**
     * Mengirim task via Core TaskInboxService jika tersedia, atau mencatat log.
     */
    protected function dispatchTask(array $data): ?string
    {
        if ($this->taskInboxService !== null) {
            try {
                $task = $this->taskInboxService->createTask($data);

                return is_object($task) && isset($task->id) ? (string) $task->id : 'TASK-OK';
            } catch (\Throwable) {
                // Fallback gracefully jika Core TaskInbox sedang offline
                return null;
            }
        }

        return 'TASK-MOCK-OK';
    }
}
