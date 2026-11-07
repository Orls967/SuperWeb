<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Application\Sagas\BlastExecutionLifecycleSaga;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Ledger\MiningFase119LedgerPostingService;

/**
 * Domain Action: Eksekusi Peledakan Terkendali Tambang (Controlled Detonation Execution).
 * Menjalankan siklus penembakan melalui BlastExecutionLifecycleSaga dan membukukan
 * biaya bahan peledak & detonator ke akun buku besar mining ledger.
 */
class ExecuteControlledDetonationAction
{
    public function __construct(
        private readonly BlastExecutionLifecycleSaga $blastSaga,
        private readonly MiningFase119LedgerPostingService $ledgerService
    ) {}

    /**
     * @param  array{
     *     blast_clearance_run_id: string,
     *     initiated_by_user_id: string,
     *     detonator_box_serial: string,
     *     explosive_anfo_kg?: float,
     *     explosive_emulsion_kg?: float,
     *     detonators_count?: int,
     *     total_explosive_cost_idr?: float
     * } $data
     * @return array{
     *     run_code: string,
     *     is_success: bool,
     *     detonated_at: string,
     *     saga_audit_trail: list<string>,
     *     ledger_journal_uuid?: string|null
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $run = MiningBlastClearanceRun::query()->findOrFail($data['blast_clearance_run_id']);

        $sagaResult = $this->blastSaga->executeBlastCycle($run, [
            'initiator' => $data['initiated_by_user_id'],
            'detonator_box' => $data['detonator_box_serial'],
            'initiated_at' => now()->toIso8601String(),
        ]);

        if (! $sagaResult['success']) {
            throw new InvalidArgumentException('Gagal mengeksekusi siklus peledakan saga: '.($sagaResult['error'] ?? 'Unknown error'));
        }

        // Posting pemakaian bahan peledak ke buku besar
        $explosiveCost = (int) round((float) ($data['total_explosive_cost_idr'] ?? 15000000));
        $journalUuid = null;

        if ($explosiveCost > 0) {
            $journalUuid = $this->ledgerService->postExplosivesConsumption(
                referenceNumber: 'BLAST-EXP-'.$run->run_code,
                blastClearanceRunId: $run->id,
                amountIdr: $explosiveCost,
                anfoKg: (float) ($data['explosive_anfo_kg'] ?? 450.0),
                emulsionKg: (float) ($data['explosive_emulsion_kg'] ?? 150.0),
                detonatorsCount: (int) ($data['detonators_count'] ?? 30),
                description: 'Konsumsi bahan peledak peledakan terkendali '.$run->run_code
            );
        }

        return [
            'run_code' => $run->run_code,
            'is_success' => true,
            'detonated_at' => now()->toIso8601String(),
            'saga_audit_trail' => $sagaResult['audit_trail'] ?? [],
            'ledger_journal_uuid' => $journalUuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['blast_clearance_run_id'])) {
            throw new InvalidArgumentException('Blast clearance run ID harus diisi.');
        }

        if (empty($data['detonator_box_serial'])) {
            throw new InvalidArgumentException('Serial mesin peledak elektronik (exploder / blasting machine) wajib disertakan.');
        }
    }
}
