<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\ExecuteLmeMarkToMarketValuationAction;
use Modules\Mining\Application\Actions\TriggerMetalsTradingMarginCallAction;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;

class MtmMetalsTradingValuationCommand extends Command
{
    protected $signature = 'mining:mtm-metals-trading
                            {contract_number? : Nomor kontrak perdagangan (kosong = evaluasi semua kontrak aktif)}
                            {--lme-cents= : Override harga settlement LME Cash (US Cents / ton)}';

    protected $description = 'Menjalankan valuasi harian Mark-to-Market lindung nilai logam bursa LME dan evaluasi margin call.';

    public function handle(
        ExecuteLmeMarkToMarketValuationAction $valuationAction,
        TriggerMetalsTradingMarginCallAction $marginCallAction
    ): int {
        $contractNumber = $this->argument('contract_number');
        $lmeCents = $this->option('lme-cents') ? (int) $this->option('lme-cents') : null;

        $query = MiningMetalsTradingContract::query();
        if ($contractNumber) {
            $query->where('contract_number', $contractNumber);
        }

        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            $this->warn('Tidak ditemukan kontrak perdagangan logam untuk dinilai.');

            return self::SUCCESS;
        }

        $this->info("Memulai valuasi Mark-to-Market untuk {$contracts->count()} kontrak perdagangan...");

        foreach ($contracts as $contract) {
            $result = $valuationAction->execute($contract, 'SHORT', $lmeCents);
            $marginCall = $marginCallAction->execute($contract);

            $this->line(sprintf(
                'Kontrak %s [%s] %s | PnL: $ %s (Rp %s) | Status Margin: %s',
                $contract->contract_number,
                $contract->metal_symbol,
                $result->positionType,
                number_format($result->unrealizedPnlUsdCents / 100, 2),
                number_format($result->unrealizedPnlIdr, 0, ',', '.'),
                $marginCall['margin_call_active'] ? '<fg=red>MARGIN CALL ACTIVATED</>' : '<fg=green>OK</>'
            ));
        }

        $this->info('Valuasi MTM posisi lindung nilai selesai.');

        return self::SUCCESS;
    }
}
