<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Finance\Contracts\LoanExposureProvider;
use Modules\Treasury\Application\Services\TreasuryEventPublisher;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Application\Services\TreasuryTaskInboxNotifier;
use Modules\Treasury\Domain\Events\FacilityDrawdownExecutedEvent;
use Modules\Treasury\Domain\Events\TreasuryCovenantBreachedEvent;
use Modules\Treasury\Domain\Exceptions\CovenantBreachException;
use Modules\Treasury\Domain\Models\CovenantWaiver;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\CreditFacilityDrawdown;
use Psr\Clock\ClockInterface;

class DrawFacilityAction
{
    public function __construct(
        protected readonly ?LoanExposureProvider $exposureProvider = null,
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
        protected readonly ?TreasuryEventPublisher $eventPublisher = null,
        protected readonly ?TreasuryTaskInboxNotifier $taskNotifier = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Penarikan fasilitas kredit bank dengan guard batas limit dan guard DER covenant.
     *
     * @return array{
     *     facility: CreditFacility,
     *     drawn_amount: int,
     *     covenant_breached: bool,
     *     warning: ?string,
     *     drawdown: CreditFacilityDrawdown
     * }
     */
    public function execute(
        CreditFacility $facility,
        int $amountIdr,
        int|float $currentDerPermille,
        ?string $idempotencyKey = null,
        string $destinationAccountCode = 'trs:bank_operational:HOLDING:IDR',
        ?int $userId = null
    ): array {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal penarikan fasilitas kredit harus lebih besar dari 0.');
        }

        if ($facility->status !== 'active') {
            throw new InvalidArgumentException("Fasilitas kredit {$facility->facility_code} tidak aktif.");
        }

        if ($facility->drawn_amount_idr + $amountIdr > $facility->credit_limit_idr) {
            throw new InvalidArgumentException('Penarikan melampaui plafon kredit yang disetujui.');
        }

        $maxDerPermil = (int) ($facility->max_der_permil ?: (int) round(((float) $facility->max_debt_equity_ratio) * 1000));
        $actualDerPermil = is_float($currentDerPermille) ? (int) round($currentDerPermille * 1000) : (int) $currentDerPermille;

        $todayStr = $this->now()->toDateString();
        $activeWaiver = CovenantWaiver::where('facility_id', $facility->id)
            ->where('status', 'approved')
            ->whereDate('valid_until', '>=', $todayStr)
            ->orderByDesc('waived_max_der_permil')
            ->first();

        $effectiveMaxDerPermil = $activeWaiver !== null
            ? max($maxDerPermil, (int) $activeWaiver->waived_max_der_permil)
            : $maxDerPermil;

        $publisher = $this->eventPublisher ?? (app()->bound(TreasuryEventPublisher::class) ? app(TreasuryEventPublisher::class) : null);

        if ($actualDerPermil > $effectiveMaxDerPermil) {
            if ($publisher !== null) {
                $publisher->publishCovenantBreached(new TreasuryCovenantBreachedEvent(
                    facilityId: (string) $facility->id,
                    facilityCode: (string) $facility->facility_code,
                    bankName: (string) $facility->bank_name,
                    covenantDerPermil: $actualDerPermil,
                    maxDerPermil: $effectiveMaxDerPermil,
                    verifiedDebtIdr: (int) $facility->drawn_amount_idr + $amountIdr,
                    verifiedEquityIdr: 1_000_000_000_000,
                    severity: 'BREACH',
                    entityCode: $facility->entity_id,
                    actorId: $userId
                ));
            }

            throw new CovenantBreachException("Penarikan ditolak: Rasio DER ({$actualDerPermil} permil) melampaui batas covenant bank ({$effectiveMaxDerPermil} permil).");
        }

        // Jika mendekati batas (>= 90%), kirim peringatan
        $nearThreshold = (int) intdiv($effectiveMaxDerPermil * 90, 100);
        if ($actualDerPermil >= $nearThreshold) {
            $notifier = $this->taskNotifier ?? (app()->bound(TreasuryTaskInboxNotifier::class) ? app(TreasuryTaskInboxNotifier::class) : null);
            if ($notifier !== null) {
                $notifier->notifyCovenantNearBreach($facility, $actualDerPermil);
            }
        }

        return DB::transaction(function () use ($facility, $amountIdr, $actualDerPermil, $effectiveMaxDerPermil, $idempotencyKey, $destinationAccountCode, $userId, $publisher) {
            $now = $this->now();

            $facility->increment('drawn_amount_idr', $amountIdr);

            $equityIdr = 1_000_000_000_000;
            $debtIdr = (int) intdiv($actualDerPermil * $equityIdr, 1000);

            /** @var CreditFacilityDrawdown $drawdown */
            $drawdown = CreditFacilityDrawdown::create([
                'facility_id' => $facility->id,
                'entity_id' => $facility->entity_id ?? 'HOLDING',
                'draw_date' => $now->toDateString(),
                'amount_idr' => $amountIdr,
                'covenant_der_permil' => $actualDerPermil,
                'max_der_permil' => $effectiveMaxDerPermil,
                'verified_debt_idr' => $debtIdr > 0 ? $debtIdr : $facility->drawn_amount_idr,
                'verified_equity_idr' => $equityIdr,
                'created_by' => $userId,
            ]);

            if ($this->ledgerPoster !== null) {
                $key = $idempotencyKey ?: "IDEM:TRS:FACILITY_DRAW:{$drawdown->id}";
                $tx = $this->ledgerPoster->postFacilityDrawdown(
                    facilityCode: $facility->facility_code,
                    amountIdr: $amountIdr,
                    destinationAccountCode: $destinationAccountCode,
                    idempotencyKey: $key,
                    facilityId: $facility->id,
                    userId: $userId
                );
                $drawdown->update(['ledger_tx_id' => $tx->id]);
            }

            if ($publisher !== null) {
                $publisher->publishFacilityDrawdown(new FacilityDrawdownExecutedEvent(
                    facilityId: (string) $facility->id,
                    facilityCode: (string) $facility->facility_code,
                    bankName: (string) $facility->bank_name,
                    drawdownAmountMinor: $amountIdr,
                    currency: 'IDR',
                    newTotalDrawnMinor: (int) $facility->fresh()->drawn_amount_idr,
                    currentDerPermille: $actualDerPermil,
                    maxDerPermille: $effectiveMaxDerPermil,
                    actorId: $userId
                ));
            }

            return [
                'facility' => $facility->fresh(),
                'drawn_amount' => $amountIdr,
                'covenant_breached' => false,
                'warning' => null,
                'drawdown' => $drawdown->fresh(),
            ];
        });
    }
}
