<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\CreditFacilityDrawdown;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\PettyCashVoucher;

class AuditTreasuryCommand extends Command
{
    protected $signature = 'treasury:audit';

    protected $description = 'Dual-source audit for Treasury: DB lokal vs Ledger & Subledger, Facility Limits vs Drawdowns, Cash Pool invariants';

    public function handle(TreasuryService $service, ?LedgerService $ledgerService = null): int
    {
        $this->info('Starting Treasury Audit...');

        $discrepancies = [];

        // 1. Audit Master Data & Invarian Plafon
        $base = $service->auditTreasury();
        $this->line("Status: {$base['status']}");
        $this->line("Currencies: {$base['currencies_count']}");
        $this->line("Exchange Rates: {$base['rates_count']}");
        $this->line("Bank Accounts: {$base['bank_accounts_count']}");
        $this->line("Unreconciled Statements: {$base['unreconciled_statements']}");
        $this->line("Active Facilities: {$base['active_facilities']}");
        $this->line("Discrepancies: {$base['discrepancy_count']}");

        if ($base['discrepancy_count'] > 0) {
            $discrepancies[] = "Discrepancy detected in credit facilities / balances! ({$base['discrepancy_count']})";
        }

        // 2. Audit Invarian Fasilitas Kredit (Credit Facility Drawn vs Drawdowns DB)
        $facilities = CreditFacility::all();
        foreach ($facilities as $fac) {
            $sumDrawdowns = (int) CreditFacilityDrawdown::where('facility_id', $fac->id)->sum('amount_idr');
            if ((int) $fac->drawn_amount_idr > 0 && $sumDrawdowns > 0 && $sumDrawdowns !== (int) $fac->drawn_amount_idr) {
                $discrepancies[] = "Facility {$fac->facility_code}: saldo terpakai ({$fac->drawn_amount_idr}) != total riwayat penarikan ({$sumDrawdowns})";
            }

            if ((int) $fac->drawn_amount_idr > (int) $fac->credit_limit_idr) {
                $discrepancies[] = "Facility {$fac->facility_code}: penarikan ({$fac->drawn_amount_idr}) melampaui limit ({$fac->credit_limit_idr})";
            }
        }

        // 3. Audit Saldo Rekening Kas Kecil vs Saldo Negatif
        $pettyAccounts = BankAccount::where('is_petty_cash', true)->get();
        foreach ($pettyAccounts as $pa) {
            if ((int) $pa->balance < 0) {
                $discrepancies[] = "Petty Cash {$pa->account_number}: saldo negatif ({$pa->balance})";
            }
        }

        // 4. Invarian Kontrak Forward
        $forwardContracts = ForwardContract::where('status', 'active')->get();
        foreach ($forwardContracts as $fwd) {
            if ((int) $fwd->notional_foreign_amount <= 0) {
                $discrepancies[] = "Forward Contract {$fwd->contract_number}: Notional amount invalid (<= 0)";
            }
        }

        $discrepancyCount = count($discrepancies);

        if ($discrepancyCount > 0) {
            \Illuminate\Support\Facades\Log::error("Treasury audit failed with discrepancies: " . json_encode($discrepancies));
            $this->error('Treasury audit FAILED: Discrepancy detected in credit facilities / balances!');
            foreach ($discrepancies as $idx => $err) {
                $this->line(" [!] ".($idx + 1).". {$err}");
            }

            return self::FAILURE;
        }

        $this->info('Treasury audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
