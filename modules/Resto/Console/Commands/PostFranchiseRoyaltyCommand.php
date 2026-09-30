<?php

declare(strict_types=1);

namespace Modules\Resto\Console\Commands;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Resto\Domain\Models\DailySummary;
use Modules\Resto\Domain\Models\OutletContract;
use Modules\Resto\Domain\Models\RoyaltyPosting;

class PostFranchiseRoyaltyCommand extends Command
{
    protected $signature = 'resto:post-royalty {--date= : Tanggal kalkulasi royalti (format YYYY-MM-DD, default hari ini)} {--outlet= : ID atau Kode outlet spesifik}';

    protected $description = 'Kalkulasi dan posting royalti franchise serta marketing fee harian dari ringkasan penjualan ke pendapatan holding grup';

    public function handle(Ledger $ledger): int
    {
        $dateStr = $this->option('date') ?: now()->toDateString();
        $targetDate = Carbon::parse($dateStr)->toDateString();

        $this->info("Menjalankan kalkulasi royalti franchise untuk tanggal {$targetDate}...");

        $contractsQuery = OutletContract::with('outlet')->where('is_active', true);
        if ($outletParam = $this->option('outlet')) {
            $contractsQuery->whereHas('outlet', function ($q) use ($outletParam) {
                $q->where('id', $outletParam)->orWhere('code', $outletParam);
            });
        }

        $contracts = $contractsQuery->get();
        if ($contracts->isEmpty()) {
            $this->warn('Tidak ada kontrak franchise aktif yang ditemukan.');

            return self::SUCCESS;
        }

        $totalPosted = 0;

        foreach ($contracts as $contract) {
            $outlet = $contract->outlet;
            if (! $outlet) {
                continue;
            }

            // Cek ringkasan penjualan harian
            $summary = DailySummary::where('outlet_id', $outlet->id)
                ->whereDate('date', $targetDate)
                ->first();

            if (! $summary || $summary->net_sales <= 0) {
                $this->line("  - Outlet {$outlet->name} ({$outlet->code}): Tidak ada omzet bersih tercatat pada {$targetDate}. Lewati.");

                continue;
            }

            // Idempotensi: cek apakah royalti sudah pernah diposting untuk tanggal ini
            $existing = RoyaltyPosting::where('outlet_id', $outlet->id)
                ->whereDate('date', $targetDate)
                ->first();

            if ($existing) {
                $this->line("  - Outlet {$outlet->name} ({$outlet->code}): Royalti tanggal {$targetDate} sudah pernah diposting. Lewati.");

                continue;
            }

            // Hitung Royalti & Marketing Fee
            $royaltyPct = (float) $contract->royalty_percent;
            $marketingPct = (float) $contract->marketing_fee_percent;

            $royaltyAmount = (int) round($summary->net_sales * ($royaltyPct / 100));
            $marketingAmount = (int) round($summary->net_sales * ($marketingPct / 100));
            $totalDeductions = $royaltyAmount + $marketingAmount;
            $franchiseeNetShare = $summary->net_sales - $totalDeductions;

            $this->line("  - Outlet {$outlet->name} ({$outlet->code}): Net Sales Rp ".number_format($summary->net_sales, 0, ',', '.').
                " | Royalti ({$royaltyPct}%): Rp ".number_format($royaltyAmount, 0, ',', '.').
                " | Marketing ({$marketingPct}%): Rp ".number_format($marketingAmount, 0, ',', '.').
                ' | Bersih Mitra: Rp '.number_format($franchiseeNetShare, 0, ',', '.'));

            $tx = null;
            if ($totalDeductions > 0) {
                $expAccCode = 'expense:resto:franchise_royalty:IDR';
                $royaltyRevCode = 'revenue:group:royalty:IDR';
                $marketingRevCode = 'revenue:group:marketing:IDR';

                $this->ensureLedgerAccountExists($expAccCode, 'Beban Royalti Franchise Mitra Resto', AccountKind::EXPENSE, true);
                $this->ensureLedgerAccountExists($royaltyRevCode, 'Pendapatan Royalti Holding Grup', AccountKind::REVENUE, false);
                $this->ensureLedgerAccountExists($marketingRevCode, 'Pendapatan Dana Pemasaran Holding Grup', AccountKind::REVENUE, false);

                $totalBd = BigDecimal::of($totalDeductions);
                $royaltyBd = BigDecimal::of($royaltyAmount);
                $marketingBd = BigDecimal::of($marketingAmount);

                $tx = $ledger->post(new PostingDTO(
                    type: TransactionType::ROYALTY->value,
                    description: "Bagi hasil royalti ({$royaltyPct}%) & marketing fee ({$marketingPct}%) outlet {$outlet->name} tgl {$targetDate}",
                    idempotencyKey: "resto:royalty:{$contract->id}:{$targetDate}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($expAccCode, 'IDR', $totalBd->negated()),
                        PostingEntryDTO::forCode($royaltyRevCode, 'IDR', $royaltyBd),
                        PostingEntryDTO::forCode($marketingRevCode, 'IDR', $marketingBd),
                    ],
                    referenceType: 'resto_contract',
                    referenceId: $contract->id,
                    createdBy: null
                ));
            }

            RoyaltyPosting::create([
                'uuid' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'outlet_id' => $outlet->id,
                'date' => $targetDate,
                'gross_sales' => $summary->gross_sales,
                'net_sales' => $summary->net_sales,
                'royalty_amount' => $royaltyAmount,
                'marketing_fee_amount' => $marketingAmount,
                'franchisee_net_share' => $franchiseeNetShare,
                'ledger_transaction_id' => $tx?->id,
            ]);

            $totalPosted++;
        }

        $this->info("✓ Selesai: Berhasil memproses {$totalPosted} posting royalti franchise.");

        return self::SUCCESS;
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind, bool $allowNegative = false): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => $allowNegative,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
