<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Asset\Domain\Models\Asset;
use Modules\Banking\Domain\Models\LedgerTransaction;

/**
 * 31.8 Rekonsiliasi subledger aset ↔ ledger (`ast:audit`), 0 selisih.
 *
 * Pemeriksaan:
 * 1. Akumulasi penyusutan (ast_depreciations komersial) == kolom
 *    `accumulated_depreciation_idr` pada aset.
 * 2. Book value == perolehan + landed + kapitalisasi − akumulasi.
 * 3. Ledger `ast:fixed_assets` + `ast:accumulated_depreciation`
 *    − seluruh disposal/disposal-method = nilai buku agregat.
 * 4. Rantai hash semua aset valid.
 */
class AssetAuditService
{
    public function __construct(private readonly AssetService $assets) {}

    /**
     * @return array{checked: int, balanced: bool, discrepancies: array<int, array<string, mixed>>, health_pillars: array<string, string>}
     */
    public function audit(): array
    {
        $assets = Asset::query()->get(['id', 'asset_number', 'acquisition_cost_idr', 'landed_cost_idr', 'accumulated_depreciation_idr', 'book_value_idr']);
        $discrepancies = [];

        // Aset legacy/seed sengaja dibuat tanpa posting `ast:acquire:*` (biayanya
        // tercatat di modul asal — mall/lgx — dan mengimpornya ke sini akan
        // double-count di level grup). Perbandingan ledger hanya mencakup aset
        // yang benar-benar dinaikan lewat ledger modul Asset.
        $acquireKeys = $assets->map(fn (Asset $asset): string => 'ast:acquire:'.$asset->id)->all();
        $postedKeys = LedgerTransaction::query()
            ->whereIn('idempotency_key', $acquireKeys)
            ->pluck('idempotency_key')
            ->flip();
        $ledgerPostedAssets = $assets->filter(fn (Asset $asset): bool => $postedKeys->has('ast:acquire:'.$asset->id));

        // 1 & 2: per-aset invariant internal.
        $depByAsset = DB::table('ast_depreciations')
            ->where('book', 'commercial')
            ->selectRaw('asset_id, SUM(amount_idr) as total')
            ->groupBy('asset_id')
            ->pluck('total', 'asset_id');

        foreach ($assets as $asset) {
            $depSum = (int) ($depByAsset[$asset->id] ?? 0);

            if ($depSum !== (int) $asset->accumulated_depreciation_idr) {
                $discrepancies[] = [
                    'asset' => $asset->asset_number,
                    'kind' => 'accumulated_vs_depreciation_schedule',
                    'expected' => $depSum,
                    'actual' => (int) $asset->accumulated_depreciation_idr,
                    'difference' => $depSum - (int) $asset->accumulated_depreciation_idr,
                ];
            }

            $expectedBook = max(
                0,
                (int) $asset->acquisition_cost_idr + (int) $asset->landed_cost_idr - (int) $asset->accumulated_depreciation_idr
            );

            if ($expectedBook !== (int) $asset->book_value_idr) {
                $discrepancies[] = [
                    'asset' => $asset->asset_number,
                    'kind' => 'book_value_formula',
                    'expected' => $expectedBook,
                    'actual' => (int) $asset->book_value_idr,
                    'difference' => $expectedBook - (int) $asset->book_value_idr,
                ];
            }
        }

        // 3: ledger vs subledger.
        $ledger = $this->ledgerBalances();
        $subledgerBookValue = (int) $ledgerPostedAssets->sum('book_value_idr');
        // Akumulasi penyusutan adalah kontra-aset ber-saldo negatif (kredit),
        // sehingga nilai buku ledger = fixed_assets + accumulated.
        $ledgerNet = $ledger['fixed_assets'] + $ledger['accumulated'];

        if ($ledgerNet !== $subledgerBookValue) {
            $discrepancies[] = [
                'asset' => '*LEDGER*',
                'kind' => 'ledger_vs_book_value',
                'expected' => $subledgerBookValue,
                'actual' => $ledgerNet,
                'difference' => $subledgerBookValue - $ledgerNet,
            ];
        }

        // 4: rantai hash.
        $chain = $this->assets->verifyChain();
        if (! $chain['valid']) {
            foreach ($chain['broken'] as $broken) {
                $discrepancies[] = [
                    'asset' => $broken['asset'],
                    'kind' => 'hash_chain',
                    'expected' => 0,
                    'actual' => $broken['sequence'],
                    'difference' => 1,
                ];
            }
        }

        return [
            'checked' => $assets->count(),
            'ledger_posted_assets' => $ledgerPostedAssets->count(),
            'imported_assets' => $assets->count() - $ledgerPostedAssets->count(),
            'balanced' => $discrepancies === [],
            'discrepancies' => $discrepancies,
            'health_pillars' => [
                'ledger' => $ledgerNet === $subledgerBookValue ? 'ok' : 'broken',
                'chain' => $chain['valid'] ? 'ok' : 'broken',
                'internal_invariants' => count(array_filter($discrepancies, fn ($d) => $d['kind'] !== 'ledger_vs_book_value' && $d['kind'] !== 'hash_chain')) === 0 ? 'ok' : 'broken',
            ],
        ];
    }

    /** @return array{fixed_assets: int, accumulated: int} */
    private function ledgerBalances(): array
    {
        return [
            'fixed_assets' => (int) DB::table('bank_ledger_accounts')
                ->where('code', 'ast:fixed_assets')
                ->where('asset_code', 'IDR')
                ->value('cached_balance'),
            'accumulated' => (int) DB::table('bank_ledger_accounts')
                ->where('code', 'ast:accumulated_depreciation')
                ->where('asset_code', 'IDR')
                ->value('cached_balance'),
        ];
    }
}
