<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Esg\Contracts\VerifiedEmissionReader;
use Modules\Logistics\Contracts\CustodyVerifier;
use Modules\TradeFinance\Application\Services\Clearing\CbamDeclarationService;

/**
 * 83.6 Audit Cross-border Clearing House: reconcile escrow crypto ledger balances,
 * unverified releases, dispute window integrity, and CBAM certificates (0 discrepancies).
 */
class AuditClearingCommand extends Command
{
    protected $signature = 'clearing:audit';

    protected $description = 'Audit Cross-Border Clearing: rekonsiliasi saldo escrow kripto, pelepasan POD, dan sertifikasi CBAM (0 selisih)';

    public function handle(): int
    {
        $rows = [];
        $issues = 0;

        // 1. Released escrows without verified POD hash
        $unverifiedReleases = DB::table('tf_crossborder_escrows')
            ->where('status', 'RELEASED')
            ->whereNull('pod_chain_hash')
            ->count();
        if ($unverifiedReleases > 0) {
            $rows[] = ['clearing.unverified_pod_release', '0', (string) $unverifiedReleases, (string) $unverifiedReleases, 'SELISIH'];
            $issues++;
        }

        // 2. Escrows in DISPUTED status with expired hold (> 24 hours) without resolution
        $unresolvedDisputes = DB::table('tf_crossborder_escrows')
            ->where('status', 'DISPUTED')
            ->whereNotNull('hold_until')
            ->where('hold_until', '<', now())
            ->count();
        if ($unresolvedDisputes > 0) {
            $rows[] = ['clearing.overdue_dispute_hold', '0', (string) $unresolvedDisputes, (string) $unresolvedDisputes, 'SELISIH'];
            $issues++;
        }

        // 3. Ledger balance reconciliation: active escrow liability vs sum of DEPOSITED and DISPUTED escrows
        if (Schema::hasTable('bank_ledger_accounts')) {
            $expectedActiveEscrowIdr = (int) DB::table('tf_crossborder_escrows')
                ->whereIn('status', ['DEPOSITED', 'DISPUTED'])
                ->selectRaw('COALESCE(SUM(ROUND((amount_cents / 100.0) * locked_fx_rate_idr)), 0) as total')
                ->value('total');

            $actualEscrowRow = DB::selectOne("SELECT cached_balance FROM bank_ledger_accounts WHERE code = 'tf:crossborder_escrow:IDR' LIMIT 1");
            $actualEscrowLedgerBalance = (int) ($actualEscrowRow?->cached_balance ?? 0);

            if ($expectedActiveEscrowIdr !== $actualEscrowLedgerBalance) {
                $diff = abs($expectedActiveEscrowIdr - $actualEscrowLedgerBalance);
                $rows[] = ['clearing.escrow_ledger_discrepancy', (string) $expectedActiveEscrowIdr, (string) $actualEscrowLedgerBalance, (string) $diff, 'SELISIH'];
                $issues++;
            }
        }

        // 4. Invalid CBAM certificates (zero or negative emissions)
        $invalidCbam = DB::table('tf_cbam_certificates')
            ->where('embedded_emissions_tco2', '<=', 0)
            ->count();
        if ($invalidCbam > 0) {
            $rows[] = ['clearing.invalid_cbam_emissions', '0', (string) $invalidCbam, (string) $invalidCbam, 'SELISIH'];
            $issues++;
        }

        // 5. D83-4 Escrow USDT per L/C: saldo akun escrow = Σ escrow terbuka; escrow dirilis = 0
        if (Schema::hasTable('tf_clearing_escrows') && Schema::hasTable('bank_ledger_accounts')) {
            $custody = app(CustodyVerifier::class);
            $escrows = DB::table('tf_clearing_escrows')->orderBy('id')->get();
            $sumOpen = 0;

            foreach ($escrows as $escrow) {
                $expected = $escrow->status === 'DEPOSITED' ? (int) $escrow->usdt_micro : 0;
                $sumOpen += $expected;
                $row = LedgerAccount::query()->where('code', "tf:escrow:{$escrow->letter_of_credit_id}:USDT")->first();
                $actual = (int) ($row->cached_balance ?? 0);

                if ($expected !== $actual) {
                    $rows[] = ["clearing.escrow_usdt_mismatch {$escrow->escrow_code}", (string) $expected, (string) $actual, (string) abs($expected - $actual), 'SELISIH'];
                    $issues++;
                }

                // Σ rilis = Σ BL/POD terverifikasi: hitung ulang lewat K6 (hash harus ada dan rantai custody valid)
                if ($escrow->status === 'RELEASED') {
                    $verified = $escrow->release_document_hash === null
                        ? null
                        : $custody->verifyDocumentHash((string) $escrow->release_document_type, (string) $escrow->release_document_hash);
                    if ($verified === null || ! $verified['chain_valid']) {
                        $rows[] = ["clearing.release_not_verified {$escrow->escrow_code}", '1', '0', '1', 'SELISIH'];
                        $issues++;
                    }
                }
            }

            $openLedger = (int) LedgerAccount::query()->where('code', 'like', 'tf:escrow:%:USDT')->sum('cached_balance');
            if ($sumOpen !== $openLedger) {
                $rows[] = ['clearing.escrow_usdt_total', (string) $sumOpen, (string) $openLedger, (string) abs($sumOpen - $openLedger), 'SELISIH'];
                $issues++;
            }
        }

        // 6. D83-3 Sertifikat CBAM per kontainer: hitung ulang emisi (K7) dan hash sertifikat
        if (Schema::hasTable('tf_cbam_declarations')) {
            $reader = app(VerifiedEmissionReader::class);
            foreach (DB::table('tf_cbam_declarations')->orderBy('id')->get() as $d) {
                $recomputed = CbamDeclarationService::embeddedMilliTco2e((int) $d->net_mass_kg, (string) $d->specific_t_co2e_per_t);
                $source = $reader->specificEmissionPerTonne($d->product_code, $d->origin_country, CarbonImmutable::parse($d->declared_at, 'UTC'));
                $sourceOk = $source !== null && (string) $source['t_co2e_per_t'] === number_format((float) $d->specific_t_co2e_per_t, 6, '.', '');
                $hash = CbamDeclarationService::certificateHash([
                    'certificate_number' => $d->certificate_number, 'container_number' => $d->container_number,
                    'product_code' => $d->product_code, 'origin_country' => $d->origin_country, 'net_mass_kg' => (int) $d->net_mass_kg,
                    'specific_t_co2e_per_t' => number_format((float) $d->specific_t_co2e_per_t, 6, '.', ''),
                    'verification_ref' => $d->verification_ref, 'embedded_milli_tco2e' => (int) $d->embedded_milli_tco2e,
                    'parameter_version' => $d->parameter_version, 'levy_idr' => (int) $d->levy_idr, 'document_checksum' => $d->document_checksum,
                ]);

                if ($recomputed !== (int) $d->embedded_milli_tco2e || ! $sourceOk || $hash !== $d->certificate_hash) {
                    $rows[] = ["clearing.cbam_certificate_mismatch {$d->certificate_number}", (string) $recomputed, (string) $d->embedded_milli_tco2e, (string) abs($recomputed - (int) $d->embedded_milli_tco2e), 'SELISIH'];
                    $issues++;
                }
            }
        }

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan audit cross-border clearing.");

            return self::FAILURE;
        }

        $this->info('✓ clearing:audit selesai: escrow stablecoin, POD custody, dan kepatuhan CBAM seimbang.');

        return self::SUCCESS;
    }
}
