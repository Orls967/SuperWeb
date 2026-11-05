<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Modules\Syariah\Domain\Models\SybParallelSalamHedge;
use Modules\Syariah\Domain\Models\SybSalamContract;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;

final class SalamMaturitySweepCommand extends Command
{
    protected $signature = 'syb:salam-maturity-sweep';

    protected $description = 'Pemeriksaan berkala jatuh tempo komoditas salam, monitoring wanprestasi dan pencocokan parallel hedge';

    public function handle(): int
    {
        $today = CarbonImmutable::now();
        $this->info("Memulai sweep jatuh tempo kontrak salam per {$today->toDateString()}...");

        $dueSoon = SybSalamContract::where('status', IslamicTradeStatus::ACTIVE->value)
            ->where('promised_delivery_date', '<=', $today->addDays(7)->toDateString())
            ->get();

        $processed = 0;
        $overdueCount = 0;

        foreach ($dueSoon as $contract) {
            $remainingQty = $contract->quantity_ordered - $contract->quantity_delivered;
            $dueDate = CarbonImmutable::parse($contract->promised_delivery_date);

            if ($dueDate->isPast() && $remainingQty > 0) {
                $overdueCount++;
                $this->warn("Kontrak Salam {$contract->contract_number} melewati jatuh tempo! Sisa kuantum belum serah: {$remainingQty} {$contract->quantity_unit}");

                SybTradeAuditLog::create([
                    'audit_ref' => $contract->contract_number,
                    'event_name' => 'SALAM_DELIVERY_OVERDUE',
                    'details' => "Kontrak salam melewati batas janji serah {$contract->promised_delivery_date}. Sisa kuantum: {$remainingQty} {$contract->quantity_unit}",
                    'shariah_compliance_status' => 'WARNING',
                ]);
            }

            // Cek parallel hedge terkait
            $hedges = SybParallelSalamHedge::where('salam_id', $contract->id)
                ->where('status', IslamicTradeStatus::ACTIVE->value)
                ->get();

            foreach ($hedges as $hedge) {
                if ($contract->quantity_delivered >= $hedge->quantity_hedged) {
                    $this->info("Parallel hedge {$hedge->hedge_contract_number} siap diserahkan ke off-taker {$hedge->offtaker_party_ref}.");
                }
            }

            $processed++;
        }

        $this->info("Sweep selesai. Diproses: {$processed} kontrak, Melewati tempo: {$overdueCount}.");

        return self::SUCCESS;
    }
}

