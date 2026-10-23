<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\PostQuantumCryptoReadinessService;

class PqAuditChainsCommand extends Command
{
    protected $signature = 'pq:audit-chains';

    protected $description = 'Audit 8 Merkle hash chains for Post-Quantum readiness — 0 discrepancy required';

    public function handle(PostQuantumCryptoReadinessService $service): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║   pq:audit-chains — Post-Quantum 8 Merkle Chains Audit       ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $result = $service->auditEightMerkleChains();

        foreach ($result['chains'] as $chain) {
            $this->line(sprintf('  %-40s : %s [%s]', $chain['chain_name'], $chain['status'], substr($chain['root_hash'], 0, 16).'...'));
        }

        $this->newLine();
        $this->line("  Total Chains Evaluated     : {$result['total_chains']}");
        $this->line("  Valid Cryptographic Chains : {$result['valid_chains_count']}");
        $this->line("  Broken / Tampered Chains   : {$result['broken_chains_count']}");

        if ($result['broken_chains_count'] > 0) {
            $this->error('pq:audit-chains FAILED — Broken Merkle hash chain detected!');

            return self::FAILURE;
        }

        $this->info('pq:audit-chains PASSED — All 8 Merkle chains verified quantum-resistant. 0 broken hops.');

        return self::SUCCESS;
    }
}
