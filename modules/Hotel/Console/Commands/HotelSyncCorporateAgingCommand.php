<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\ReconcileCityLedgerCorporateAgingAction;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;

class HotelSyncCorporateAgingCommand extends Command
{
    protected $signature = 'hotel:corporate-aging-sync';

    protected $description = 'Sinkronisasi dan kalkulasi matriks aging AR piutang korporat City Ledger hotel';

    public function handle(ReconcileCityLedgerCorporateAgingAction $agingAction): int
    {
        $this->info('Memulai kalkulasi matriks aging AR piutang korporat...');

        $accounts = HtlCorporateAccount::all();
        if ($accounts->isEmpty()) {
            $this->warn('Tidak ada akun korporat terdaftar.');

            return self::SUCCESS;
        }

        foreach ($accounts as $acc) {
            $matrix = $agingAction->execute((string) $acc->id);
            $this->line("Korporat [{$acc->company_name}]: Current: IDR {$matrix['current_0_30']}, 30-60: IDR {$matrix['days_31_60']}, 60+: IDR {$matrix['days_61_plus']}, Status: {$matrix['account_status']}");
        }

        $this->info('Sinkronisasi aging piutang korporat selesai.');

        return self::SUCCESS;
    }
}
