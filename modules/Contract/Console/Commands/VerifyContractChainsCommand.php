<?php

declare(strict_types=1);

namespace Modules\Contract\Console\Commands;

use Illuminate\Console\Command;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Exceptions\ContractChainCorruptedException;

class VerifyContractChainsCommand extends Command
{
    protected $signature = 'contracts:verify-chain {--contract= : Optional UUID of specific contract to verify}';

    protected $description = 'Verify the append-only cryptographic hash-chain across all contract versions';

    public function handle(ContractService $contractService): int
    {
        $this->info('Memulai verifikasi integritas hash-chain versi kontrak...');

        $query = Contract::query();
        if ($contractId = $this->option('contract')) {
            $query->where('id', $contractId);
        }

        $totalChecked = 0;
        $corrupted = 0;

        $contracts = $query->with('versions')->get();
        foreach ($contracts as $contract) {
            $totalChecked++;
            try {
                $contractService->verifyHashChain($contract);
                $this->line("  [✓] {$contract->contract_number} ({$contract->versions->count()} versi): Valid.");
            } catch (ContractChainCorruptedException $e) {
                $corrupted++;
                $this->error("  [✗] {$contract->contract_number}: RUSAK! {$e->getMessage()}");
            }
        }

        if ($corrupted > 0) {
            $this->error("Verifikasi gagal: Ditemukan {$corrupted} kontrak dengan rantai hash yang korup/dimanipulasi!");

            return self::FAILURE;
        }

        $this->info("✓ Seluruh {$totalChecked} kontrak memiliki rantai hash-chain yang utuh dan terverifikasi.");

        return self::SUCCESS;
    }
}
