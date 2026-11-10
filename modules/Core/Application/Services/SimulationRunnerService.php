<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Modules\Core\Contracts\SimulationRunner;
use Modules\Core\Domain\Algorithms\Simulation\DiscreteEventScheduler;
use Modules\Core\Domain\Algorithms\Simulation\StreamSeedAndFingerprint;
use Modules\Core\Domain\Exceptions\CorruptedCheckpointException;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\StateMachines\SimulationRunStateMachine;
use Modules\Core\Domain\StateMachines\SimulationRunStatus;
use Symfony\Component\Console\Output\BufferedOutput;

class SimulationRunnerService implements SimulationRunner
{
    public const CHECKPOINT_INTERVAL = 50;

    public function __construct(
        protected SimClockInterface $simClock,
        protected SimTickRegistry $tickRegistry,
        protected SimStreamRegistryService $streamRegistry,
        protected SimulationRunStateMachine $stateMachine,
        protected DigitalTwinService $twinService,
        protected StreamSeedAndFingerprint $fingerprintAlgo = new StreamSeedAndFingerprint,
        protected ?OutboxBusInterface $outboxBus = null,
    ) {}

    /**
     * Memulai sesi simulasi baru secara deterministik.
     */
    public function start(string $name, int $days, int $seed, string $mode = 'live'): string|int
    {
        $mode = in_array(strtolower($mode), ['sandbox', 'sim'], true) ? 'sandbox' : 'live';

        // 1. Inisialisasi SimRun
        $startDate = Carbon::parse('2026-01-01 00:00:00');
        $existing = SimRun::where('name', $name)->first();
        if ($existing) {
            $run = $existing;
            $run->status = SimulationRunStatus::DRAFT->value;
            $run->total_days = $days;
            $run->current_day = 0;
            $run->last_completed_day = 0;
            $run->seed = $seed;
            $run->mode = $mode;
            $run->chain_hash = null;
            $run->sim_start_at = $startDate;
            $run->virtual_now = $startDate;
            $run->save();

            SimCheckpoint::where('run_id', $run->id)->delete();
        } else {
            $run = $this->simClock->initRun($name, $startDate, $seed);
            $run->status = SimulationRunStatus::DRAFT->value;
            $run->total_days = $days;
            $run->current_day = 0;
            $run->last_completed_day = 0;
            $run->mode = $mode;
            $run->save();
        }

        // Transisi: DRAFT -> RUNNING
        $this->stateMachine->transition(
            $run,
            SimulationRunStatus::RUNNING,
            "Sesi simulasi {$name} dimulai untuk {$days} hari dengan seed {$seed} (mode: {$mode})."
        );

        // 2. Setup Aliran Acak & Fingerprint Awal
        $this->simClock->setActiveRun($run);
        $this->streamRegistry->setActiveSeed($seed);
        $chainHash = $this->fingerprintAlgo->initialChainHash($seed);
        $run->chain_hash = $chainHash;
        $run->last_state_hash = $chainHash;
        $run->save();

        // 3. Eksekusi Harian
        if ($mode === 'sandbox') {
            $chainHash = $this->executeSandboxRun($run, 1, $days, $chainHash);
        } else {
            $chainHash = $this->executeLiveRun($run, 1, $days, $chainHash);
        }

        // Transisi: RUNNING -> COMPLETED
        $run->refresh();
        $this->stateMachine->transition(
            $run,
            SimulationRunStatus::COMPLETED,
            "Simulasi {$name} tuntas hingga hari ke-{$days}."
        );

        $run->chain_hash = $chainHash;
        $run->save();

        return $run->id;
    }

    /**
     * Melanjutkan sesi simulasi yang dijeda atau terputus dari checkpoint terakhir.
     */
    public function resume(string|int $runId, ?int $additionalDays = null): void
    {
        $run = is_numeric($runId)
            ? SimRun::find($runId)
            : (SimRun::where('id', $runId)->first() ?? SimRun::where('name', (string) $runId)->first());

        if (! $run) {
            throw new \InvalidArgumentException("Sesi simulasi ID/Nama [{$runId}] tidak ditemukan.");
        }

        // Cari checkpoint terakhir
        $checkpoint = SimCheckpoint::where('run_id', $run->id)
            ->orderBy('day_number', 'desc')
            ->first();

        if (! $checkpoint) {
            // Jika belum ada di tabel sim_checkpoints, cek apakah ada last_completed_day
            if ($run->last_completed_day > 0 && ! empty($run->chain_hash)) {
                $startDay = $run->last_completed_day + 1;
                $currentChainHash = $run->chain_hash;
            } else {
                throw new CorruptedCheckpointException("Tidak ditemukan checkpoint yang valid untuk resume sesi [{$run->name}].");
            }
        } else {
            $startDay = $checkpoint->day_number + 1;
            $currentChainHash = $checkpoint->chain_hash;

            // Verifikasi integritas checkpoint
            if (empty($currentChainHash)) {
                throw new CorruptedCheckpointException('Hash rantai pada checkpoint kosong atau terkorupsi.');
            }
        }

        if ($additionalDays !== null && $additionalDays > 0) {
            $targetDays = ($startDay - 1) + $additionalDays;
        } else {
            $targetDays = $run->total_days > $startDay ? $run->total_days : 365;
        }
        $run->total_days = $targetDays;

        $this->stateMachine->transition(
            $run,
            SimulationRunStatus::RUNNING,
            "Melanjutkan simulasi {$run->name} dari hari {$startDay} hingga {$targetDays}."
        );

        $this->streamRegistry->setActiveSeed((int) $run->seed);
        $this->simClock->setActiveRun($run);

        if ($run->mode === 'sandbox') {
            $finalHash = $this->executeSandboxRun($run, $startDay, $targetDays, $currentChainHash);
        } else {
            $finalHash = $this->executeLiveRun($run, $startDay, $targetDays, $currentChainHash);
        }

        $run->refresh();
        $this->stateMachine->transition(
            $run,
            SimulationRunStatus::COMPLETED,
            "Simulasi lanjutan {$run->name} selesai pada hari ke-{$targetDays}."
        );

        $run->chain_hash = $finalHash;
        $run->save();
    }

    /**
     * Mengambil fingerprint rantai kriptografis terbaru dari sesi simulasi.
     */
    public function fingerprint(string|int $runId): string
    {
        $run = is_numeric($runId)
            ? SimRun::find($runId)
            : (SimRun::where('id', $runId)->first() ?? SimRun::where('name', (string) $runId)->first());

        if (! $run) {
            throw new \InvalidArgumentException("Sesi simulasi ID/Nama [{$runId}] tidak ditemukan.");
        }

        return (string) $run->chain_hash;
    }

    /**
     * Eksekusi loop hari simulasi dalam mode Live.
     */
    protected function executeLiveRun(SimRun $run, int $startDay, int $endDay, string $initialChainHash): string
    {
        $currentChain = $initialChainHash;
        $handlers = $this->tickRegistry->resolveExecutionOrder();

        for ($day = $startDay; $day <= $endDay; $day++) {
            $virtualNow = Carbon::parse('2026-01-01 00:00:00')->addDays($day);
            $this->simClock->travel($virtualNow);
            $dailySummary = $this->executeDayWithScheduler($virtualNow, $day, $handlers);

            $currentChain = $this->fingerprintAlgo->nextChainHash($currentChain, $day, $dailySummary);

            $run->current_day = $day;
            $run->last_completed_day = $day;
            $run->chain_hash = $currentChain;
            $run->last_state_hash = $currentChain;
            $run->virtual_now = $virtualNow;
            $run->save();

            // Tulis checkpoint pada interval atau akhir hari
            if ($day % self::CHECKPOINT_INTERVAL === 0 || $day === $endDay || $day === 200) {
                $this->saveCheckpoint($run, $day, $currentChain, $dailySummary);
            }
        }

        return $currentChain;
    }

    /**
     * Eksekusi loop hari simulasi dalam mode Sandbox (IZIN-4 / W8-D35).
     * Seluruh transaksi DB di-rollback setelah audit keseimbangan ledger.
     */
    protected function executeSandboxRun(SimRun $run, int $startDay, int $endDay, string $initialChainHash): string
    {
        $currentChain = $initialChainHash;
        $handlers = $this->tickRegistry->resolveExecutionOrder();

        // 1. Catat state ledger sebelum eksekusi sandbox
        $ledgerProbeInitial = $this->probeLedgerBalance();

        DB::beginTransaction();
        try {
            for ($day = $startDay; $day <= $endDay; $day++) {
                $virtualNow = Carbon::parse('2026-01-01 00:00:00')->addDays($day);
                $this->simClock->travel($virtualNow);
                $dailySummary = $this->executeDayWithScheduler($virtualNow, $day, $handlers);
                $currentChain = $this->fingerprintAlgo->nextChainHash($currentChain, $day, $dailySummary);
            }

            // Probe keseimbangan debit dan kredit selama run
            $ledgerProbeDuring = $this->probeLedgerBalance();
            $deltaDebitCredit = abs($ledgerProbeDuring['total_debit'] - $ledgerProbeDuring['total_credit']);
            if ($deltaDebitCredit !== 0) {
                throw new \RuntimeException("PROBE_VIOLATION: Ketidakseimbangan buku besar ditemukan (selisih debit-kredit: {$deltaDebitCredit}).");
            }

            // W8-D35: Selalu rollback seluruh mutasi bisnis pada mode sandbox
            DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Verifikasi bahwa saldo ledger riil tidak tersentuh
        $ledgerProbeFinal = $this->probeLedgerBalance();
        if ($ledgerProbeFinal['entry_count'] !== $ledgerProbeInitial['entry_count']) {
            throw new \RuntimeException('SANDBOX_LEAK: Terdeteksi kebocoran data sandbox ke tabel buku besar riil.');
        }

        // Tulis rekaman status dan fingerprint akhir di luar transaksi rollback
        $run->current_day = $endDay;
        $run->last_completed_day = $endDay;
        $run->chain_hash = $currentChain;
        $run->last_state_hash = $currentChain;
        $run->save();

        $this->saveCheckpoint($run, $endDay, $currentChain, ['mode' => 'sandbox', 'days' => $endDay]);

        return $currentChain;
    }

    /**
     * Eksekusi tick harian menggunakan DiscreteEventScheduler (A1).
     *
     * @param  list<SimTickHandlerInterface>  $handlers
     * @return array<string, mixed>
     */
    protected function executeDayWithScheduler(CarbonInterface $virtualNow, int $day, array $handlers): array
    {
        $scheduler = new DiscreteEventScheduler;
        $dailySummary = [];

        // Jadwalkan setiap handler pada 00:00:00 virtual dengan prioritas parameter
        $prio = 1;
        foreach ($handlers as $handler) {
            if ($handler->shouldRun($virtualNow, $day)) {
                $scheduler->schedule(0, $prio, $handler->name());
                $prio++;
            }
        }

        // Proses peristiwa diskrit secara berurutan
        while (! $scheduler->isEmpty()) {
            $event = $scheduler->poll();
            if ($event === null) {
                break;
            }

            $handlerName = $event->name;
            $handlerInstance = $this->tickRegistry->all()[$handlerName] ?? null;

            if ($handlerInstance) {
                $subOutput = new BufferedOutput;
                $res = $handlerInstance->handle(
                    $virtualNow,
                    $day,
                    fn (string $cmd, array $args = []): int => Artisan::call($cmd, $args, $subOutput)
                );
                // Ekstrak ringkasan integer/string kanonik
                $dailySummary[$handlerName] = is_array($res) ? ($res['processed'] ?? ($res['status'] ?? 1)) : 1;
            }
        }

        ksort($dailySummary);

        return $dailySummary;
    }

    /**
     * Menyimpan checkpoint simulasi pada basis data.
     *
     * @param  array<string, mixed>  $summary
     */
    protected function saveCheckpoint(SimRun $run, int $day, string $chainHash, array $summary): SimCheckpoint
    {
        return SimCheckpoint::updateOrCreate(
            [
                'run_id' => $run->id,
                'day_number' => $day,
            ],
            [
                'chain_hash' => $chainHash,
                'handler_hashes' => $summary,
                'state_summary' => [
                    'day' => $day,
                    'virtual_now' => $run->virtual_now?->toIso8601String(),
                    'seed' => $run->seed,
                    'chain_hash' => $chainHash,
                ],
            ]
        );
    }

    /**
     * Probe internal untuk memeriksa saldo pembukuan double-entry.
     *
     * @return array{total_debit: int, total_credit: int, entry_count: int}
     */
    public function probeLedgerBalance(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('bank_ledger_entries')) {
            return ['total_debit' => 0, 'total_credit' => 0, 'entry_count' => 0];
        }

        $bankTable = 'bank_ledger_entries';
        $stats = DB::table($bankTable)
            ->selectRaw('COALESCE(SUM(amount), 0) as net_balance, COUNT(*) as count')
            ->first();

        $net = (int) ($stats->net_balance ?? 0);

        return [
            'total_debit' => max(0, $net),
            'total_credit' => max(0, -$net),
            'entry_count' => (int) ($stats->count ?? 0),
        ];
    }
}
