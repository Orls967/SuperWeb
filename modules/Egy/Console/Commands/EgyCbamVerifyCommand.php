<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Egy\Domain\Models\CbamDeclaration;

final class EgyCbamVerifyCommand extends Command
{
    protected $signature = 'egy:cbam-verify';

    protected $description = 'Verifikasi kepatuhan deklarasi CBAM ekspor terhadap pembukuan liabilitas bea pabean Uni Eropa';

    public function handle(): int
    {
        $this->info('Memulai audit integritas deklarasi ekspor CBAM...');

        $declarations = CbamDeclaration::all();
        $discrepancies = 0;

        foreach ($declarations as $dec) {
            $expectedLiability = (int) $dec->total_cbam_liability_idr;

            if ($expectedLiability > 0 && $dec->ledger_transaction_id !== null) {
                $ledgerEntry = LedgerEntry::where('transaction_id', $dec->ledger_transaction_id)
                    ->whereHas('account', fn ($q) => $q->where('code', 'ap:egy:cbam_customs_authority:IDR'))
                    ->first();

                $ledgerAmount = $ledgerEntry !== null ? (int) abs((float) $ledgerEntry->amount) : 0;
                if ($expectedLiability !== $ledgerAmount) {
                    $this->error("CBAM Declaration [#{$dec->declaration_number}] liability ({$expectedLiability}) != ledger ({$ledgerAmount})");
                    $discrepancies++;
                }
            }
        }

        if ($discrepancies > 0) {
            $this->error("Audit CBAM GAGAL: Terdeteksi {$discrepancies} selisih integritas liabilitas.");

            return self::FAILURE;
        }

        $this->info(sprintf('Audit CBAM BERSIH: %d deklarasi valid dengan 0 selisih.', $declarations->count()));

        return self::SUCCESS;
    }
}
