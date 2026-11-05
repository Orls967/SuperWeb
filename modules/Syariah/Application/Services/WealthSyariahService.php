<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Shared\Domain\Algorithms\Islamic\ShariahScreening;
use Modules\Syariah\Domain\Enums\WealthRiskProfile;
use Modules\Syariah\Domain\Models\SybWealthInstrument;
use Modules\Syariah\Domain\Models\SybWealthOrder;
use Modules\Syariah\Domain\Models\SybWealthPortfolio;
use Modules\Syariah\Ledger\SyariahSubjectAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class WealthSyariahService
{
    public function __construct(
        private readonly ShariahLedgerPoster $poster,
        private readonly ClockInterface $clock,
        private readonly ShariahScreening $screening = new ShariahScreening
    ) {}

    /**
     * Pendaftaran dan screening syariah instrumen efek (B4).
     */
    public function registerInstrument(array $data, User $advisor): SybWealthInstrument
    {
        $ticker = strtoupper(trim((string) $data['ticker_symbol']));
        $name = trim((string) $data['name']);
        $debt = (int) ($data['interest_bearing_debt_idr'] ?? 0);
        $assets = (int) ($data['total_assets_idr'] ?? 1);
        $nonHalal = (int) ($data['non_halal_revenue_idr'] ?? 0);
        $revenue = (int) ($data['total_revenue_idr'] ?? 1);
        $sector = (string) ($data['sector_code'] ?? 'GENERAL_COMMERCE');

        $screeningResult = $this->screening->screen(
            interestBearingDebtIdr: $debt,
            totalAssetsIdr: $assets,
            nonHalalRevenueIdr: $nonHalal,
            totalRevenueIdr: $revenue,
            sectorCode: $sector
        );

        return SybWealthInstrument::updateOrCreate(
            ['ticker_symbol' => $ticker],
            [
                'name' => $name,
                'instrument_type' => (string) ($data['instrument_type'] ?? 'SHARIAH_EQUITY'),
                'interest_bearing_debt_idr' => $debt,
                'total_assets_idr' => $assets,
                'debt_ratio_bps' => $screeningResult->debtRatioBps,
                'non_halal_revenue_idr' => $nonHalal,
                'total_revenue_idr' => $revenue,
                'non_halal_revenue_ratio_bps' => $screeningResult->nonHalalRevenueRatioBps,
                'sector_code' => $sector,
                'is_shariah_compliant' => $screeningResult->isCompliant,
                'last_screened_at' => $this->clock->now(),
            ]
        );
    }

    /**
     * Pembuatan portofolio nasabah wealth syariah terikat profil risiko.
     */
    public function createPortfolio(array $data, User $customer): SybWealthPortfolio
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        $profile = WealthRiskProfile::from((string) ($data['risk_profile'] ?? 'MODERATE'));
        $deposit = (int) ($data['initial_deposit_idr'] ?? 0);

        return DB::transaction(function () use ($customer, $profile, $deposit) {
            return SybWealthPortfolio::create([
                'user_id' => $customer->id,
                'risk_profile' => $profile,
                'current_balance_idr' => $deposit,
                'total_invested_idr' => 0,
                'total_purification_idr' => 0,
                'status' => 'ACTIVE',
            ]);
        });
    }

    /**
     * Eksekusi order beli instrumen wealth syariah dengan gerbang screening ketat (D162-3).
     */
    public function placeOrder(array $data, User $customer): SybWealthOrder
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = SybWealthOrder::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $instrumentId = (int) $data['instrument_id'];
        /** @var SybWealthInstrument $instrument */
        $instrument = SybWealthInstrument::findOrFail($instrumentId);

        // Gerbang kepatuhan syariah wajib sebelum transaksi pembelian
        if (! $instrument->is_shariah_compliant) {
            throw new DomainException('NON_SHARIAH_COMPLIANT_INSTRUMENT: Pembelian ditolak karena instrumen tidak memenuhi kriteria screening syariah AAOIFI/OJK.');
        }

        return DB::transaction(function () use ($data, $customer, $instrument, $key) {
            /** @var SybWealthPortfolio $portfolio */
            $portfolio = SybWealthPortfolio::query()
                ->where('user_id', $customer->id)
                ->firstOrFail();

            $amount = (int) $data['amount_idr'];
            $units = (int) $data['units'];

            $order = SybWealthOrder::create([
                'portfolio_id' => $portfolio->id,
                'instrument_id' => $instrument->id,
                'order_type' => 'BUY',
                'units' => $units,
                'amount_idr' => $amount,
                'status' => 'EXECUTED',
                'idempotency_key' => $key,
            ]);

            $portfolio->update([
                'total_invested_idr' => $portfolio->total_invested_idr + $amount,
            ]);

            return $order;
        });
    }

    /**
     * Purifikasi dividen non-halal otomatis ke dana kebajikan (D162-6).
     */
    public function purifyDividend(
        int $portfolioId,
        int $instrumentId,
        int $dividendAmountIdr,
        string $idempotencyKey,
        User $investor
    ): array {
        /** @var SybWealthInstrument $instrument */
        $instrument = SybWealthInstrument::findOrFail($instrumentId);

        $purify = $this->screening->purifyDividend($dividendAmountIdr, $instrument->non_halal_revenue_ratio_bps);
        $charityAmount = $purify['purification_charity_idr'];

        if ($charityAmount > 0) {
            $investorWallet = SyariahSubjectAccounts::customerWallet($investor->id);

            // Debit wallet:user:{investor}:IDR, Kredit syb:charity_fund:IDR
            $this->poster->post(new PostingDTO(
                type: SyariahTransactionTypes::SYB_DIVIDEND_PURIFICATION->value,
                description: "Purifikasi dividen {$instrument->ticker_symbol} ke dana kebajikan",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit($investorWallet, $charityAmount, 'IDR')
                    ->credit('syb:charity_fund:IDR', $charityAmount, 'IDR')
                    ->toEntries('IDR'),
                referenceType: 'SYB_WEALTH_PURIFICATION',
                referenceId: (string) $portfolioId,
            ));

            SybWealthPortfolio::query()->where('id', $portfolioId)->increment('total_purification_idr', $charityAmount);
        }

        return $purify;
    }
}
