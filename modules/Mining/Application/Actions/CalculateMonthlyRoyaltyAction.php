<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Mining\Ledger\MiningAccounts;
use Modules\Mining\Ledger\MiningTransactionTypes;
use Modules\Shared\Application\BaseAction;

class CalculateMonthlyRoyaltyAction extends BaseAction
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * Compute statutory royalty using integer math formula (T-6).
     *
     * round_half_up(tonase_kg * price_usd_cents_per_ton * exchange_rate_idr_1e6 * tariff_bps / 10^15)
     */
    public function computeRoyalty(
        int $tonnageKg,
        int $priceUsdCentsPerTon,
        int $exchangeRateIdr1e6,
        int $tariffBps
    ): int {
        if ($tonnageKg <= 0 || $priceUsdCentsPerTon <= 0 || $exchangeRateIdr1e6 <= 0 || $tariffBps <= 0) {
            return 0;
        }

        // Numerator = tonnageKg * price_usd_cents * rate_1e6 * tariff_bps
        $t = BigDecimal::of($tonnageKg);
        $p = BigDecimal::of($priceUsdCentsPerTon);
        $r = BigDecimal::of($exchangeRateIdr1e6);
        $bps = BigDecimal::of($tariffBps);

        $num = $t->multipliedBy($p)->multipliedBy($r)->multipliedBy($bps);
        $den = BigDecimal::of('1000000000000000'); // 1000 * 100 * 1,000,000 * 10,000 = 10^15

        $rational = BigRational::of("{$num}/{$den}");

        return $rational->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    public function resolvePeriodFromUtc(string $utcTimestamp, string $timezone): string
    {
        return Carbon::parse($utcTimestamp, 'UTC')->setTimezone($timezone)->format('Y-m');
    }

    /**
     * @return object{royaltyReturn: MiningRoyaltyReturn}
     */
    public function handle(
        string $siteId,
        string $commodity,
        string $periodLocal,
        int $priceUsdCentsPerTon,
        int $exchangeRateIdr1e6,
        int $tariffBps,
        string $idempotencyKey = ''
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = MiningRoyaltyReturn::where('site_id', $siteId)
            ->where('period_local', $periodLocal)
            ->first();

        if ($existing) {
            return (object) ['royaltyReturn' => $existing];
        }

        return DB::transaction(function () use (
            $siteId,
            $commodity,
            $periodLocal,
            $priceUsdCentsPerTon,
            $exchangeRateIdr1e6,
            $tariffBps,
            $idempotencyKey
        ) {
            $site = MiningSite::findOrFail($siteId);
            $timezone = $site->timezone ?: 'Asia/Makassar';

            // Calculate total verified production tonnage from DB weighbridge tickets for this local period
            $tickets = WeighbridgeTicket::where('site_id', $siteId)->get();
            $totalProductionKg = 0;

            foreach ($tickets as $ticket) {
                $weighedAt = $ticket->weighed_at ?: $ticket->created_at;
                $ticketLocalPeriod = Carbon::parse($weighedAt)->setTimezone($timezone)->format('Y-m');
                if ($ticketLocalPeriod === $periodLocal) {
                    $totalProductionKg += (int) $ticket->net_weight_kg;
                }
            }

            $royaltyDueIdr = $this->computeRoyalty(
                $totalProductionKg,
                $priceUsdCentsPerTon,
                $exchangeRateIdr1e6,
                $tariffBps
            );

            $royaltyReturn = MiningRoyaltyReturn::create([
                'id' => (string) Str::uuid(),
                'return_uuid' => $idempotencyKey,
                'site_id' => $siteId,
                'commodity' => $commodity,
                'period_local' => $periodLocal,
                'total_production_kg' => $totalProductionKg,
                'benchmark_price_usd_cents_per_ton' => $priceUsdCentsPerTon,
                'exchange_rate_idr_per_usd_1e6' => $exchangeRateIdr1e6,
                'royalty_rate_bps' => $tariffBps,
                'calculated_royalty_idr' => $royaltyDueIdr,
                'finalized_royalty_idr' => null,
                'delta_royalty_idr' => 0,
                'status' => 'PROVISIONAL',
            ]);

            // Post accrual entries to Banking double-entry ledger
            if ($royaltyDueIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: MiningTransactionTypes::MINING_ROYALTY_ACCRUAL->value,
                    description: "Akrual royalti Minerba {$commodity} {$periodLocal} ({$totalProductionKg} kg)",
                    idempotencyKey: "min:roy:accrue:{$siteId}:{$periodLocal}",
                    entries: Posting::lines()
                        ->debit(MiningAccounts::royaltyExpense(), $royaltyDueIdr)
                        ->credit(MiningAccounts::royaltyPayable(), $royaltyDueIdr)
                        ->toEntries('IDR'),
                    referenceType: 'MINING_ROYALTY',
                    referenceId: $royaltyReturn->id,
                ));
            }

            return (object) ['royaltyReturn' => $royaltyReturn];
        });
    }
}
