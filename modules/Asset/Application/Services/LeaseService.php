<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetLease;
use Modules\Asset\Domain\Models\AssetLeasePayment;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;

/**
 * 31.6 Sewa (PSAK 73 simulasi): hak guna aset & liabilitas sewa dari
 * kontrak Fase 29, dengan amortisasi bunga per periode.
 *
 * Model sederhana: setiap periode membayar `periodic_payment_idr`,
 * dipecah menjadi bunga (perkiraan implisit) dan pelunasan pokok.
 */
class LeaseService
{
    private const ROU_ASSET = 'ast:right_of_use';

    private const LEASE_LIABILITY = 'ast:lease_liability';

    private const LEASE_INTEREST = 'ast:lease_interest';

    public function __construct(private readonly Ledger $ledger) {}

    /**
     * Buat sewa + jadwal pembayaran (idempoten per aset).
     *
     * @param array{contract_id?: string, lease_kind?: string, periodic_payment_idr?: int,
     *   total_periods?: int, implicit_rate?: float, start_date: string} $data
     */
    public function start(Asset $asset, array $data): AssetLease
    {
        return DB::transaction(function () use ($asset, $data) {
            $existing = AssetLease::query()
                ->where('asset_id', $asset->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $periods = max(1, (int) ($data['total_periods'] ?? 36));
            $payment = (int) ($data['periodic_payment_idr'] ?? 0);
            $rate = (float) ($data['implicit_rate'] ?? 0);

            if ($payment <= 0) {
                throw new InvalidArgumentException('Pembayaran periode sewa harus lebih besar dari 0.');
            }

            // PV sederhana: pokok sewa = Σ pembayaran / (1+r)^n (PSAK 73 simulasi).
            $liability = BigDecimal::zero();
            for ($n = 1; $n <= $periods; $n++) {
                // pow() menghasilkan float; konversi eksplisit ke string karena
                // BigDecimal menolak float (aturan no-float uang).
                $divisor = sprintf('%.12F', pow(1 + ($rate / 100), $n));
                $liability = $liability->plus(
                    BigDecimal::of($payment)->dividedBy($divisor, 6, RoundingMode::HalfUp)
                );
            }

            $liability = $liability->toScale(0, RoundingMode::HalfUp);

            $lease = AssetLease::create([
                'asset_id' => $asset->id,
                'contract_id' => $data['contract_id'] ?? null,
                'lease_kind' => $data['lease_kind'] ?? 'finance',
                'right_of_use_asset_idr' => (int) $liability->__toString(),
                'lease_liability_idr' => (int) $liability->__toString(),
                'periodic_payment_idr' => $payment,
                'total_periods' => $periods,
                'elapsed_periods' => 0,
                'amortized_interest_idr' => 0,
                'implicit_rate' => $rate,
                'start_date' => $data['start_date'],
                'end_date' => Carbon::parse($data['start_date'])->addMonths($periods)->toDateString(),
                'status' => 'active',
            ]);

            // Posting pembukaan sewa: debit hak guna, kredit liabilitas sewa.
            $this->ensureAccounts();
            $this->ledger->post(new PostingDTO(
                type: TransactionType::ASSET_LEASE_AMORT->value,
                description: "Pembukaan sewa aset {$asset->asset_number} (PSAK 73 simulasi)",
                idempotencyKey: 'ast:lease:start:'.$lease->id,
                entries: [
                    PostingEntryDTO::forCode(self::ROU_ASSET, 'IDR', BigDecimal::of($lease->right_of_use_asset_idr)),
                    PostingEntryDTO::forCode(self::LEASE_LIABILITY, 'IDR', BigDecimal::of($lease->lease_liability_idr)->negated()),
                ],
                referenceType: AssetLease::class,
                referenceId: $lease->id,
                meta: ['asset_id' => $asset->id, 'periods' => $periods, 'rate' => $rate, 'simulated' => true],
                postedAt: now(),
            ));

            // Jadwal pembayaran.
            $balance = $liability;
            $start = Carbon::parse($data['start_date']);

            for ($n = 1; $n <= $periods; $n++) {
                // Konversi float rate → string (BigDecimal menolak float).
                $interest = $balance->multipliedBy(sprintf('%.6F', $rate))
                    ->dividedBy(100, 0, RoundingMode::HalfUp);
                $principal = BigDecimal::of($payment)->minus($interest);
                $balance = $balance->minus($principal);

                AssetLeasePayment::create([
                    'lease_id' => $lease->id,
                    'period_no' => $n,
                    'due_date' => $start->copy()->addMonths($n)->toDateString(),
                    'payment_idr' => $payment,
                    'interest_portion_idr' => (int) $interest->__toString(),
                    'principal_portion_idr' => (int) $principal->__toString(),
                    'status' => 'pending',
                ]);
            }

            $this->assets()->recordEvent($asset, AssetEventType::Lease, [
                'lease_id' => $lease->id,
                'liability_idr' => $lease->lease_liability_idr,
                'periods' => $periods,
                'rate_percent' => $rate,
                'simulated_psa73' => true,
            ]);

            return $lease;
        });
    }

    /**
     * Bayar satu periode sewa (idempoten): bunga → beban, pokok → kredit liabilitas.
     */
    public function payPeriod(AssetLeasePayment $payment, ?int $payerUserId = null): AssetLeasePayment
    {
        return DB::transaction(function () use ($payment, $payerUserId) {
            /** @var AssetLeasePayment $locked */
            $locked = AssetLeasePayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status === 'paid') {
                return $locked;
            }

            $interest = (int) $locked->interest_portion_idr;
            $principal = (int) $locked->principal_portion_idr;

            $this->ensureAccounts();

            $this->ledger->post(new PostingDTO(
                type: TransactionType::ASSET_LEASE_AMORT->value,
                description: "Bayar sewa periode {$locked->period_no} lease #{$locked->lease_id}",
                idempotencyKey: 'ast:lease:pay:'.$locked->id,
                entries: [
                    PostingEntryDTO::forCode(self::LEASE_INTEREST, 'IDR', BigDecimal::of($interest)),
                    PostingEntryDTO::forCode(self::LEASE_LIABILITY, 'IDR', BigDecimal::of($principal)),
                    PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', BigDecimal::of($locked->payment_idr)->negated()),
                ],
                referenceType: AssetLeasePayment::class,
                referenceId: $locked->id,
                meta: ['lease_id' => $locked->lease_id, 'period_no' => $locked->period_no],
                createdBy: $payerUserId,
                postedAt: now(),
            ));

            $locked->status = 'paid';
            $locked->paid_at = now();
            $locked->save();

            /** @var AssetLease $lease */
            $lease = AssetLease::query()->lockForUpdate()->findOrFail($locked->lease_id);
            $lease->elapsed_periods = (int) $lease->elapsed_periods + 1;
            $lease->lease_liability_idr = max(0, (int) $lease->lease_liability_idr - $principal);
            $lease->amortized_interest_idr = (int) $lease->amortized_interest_idr + $interest;
            $lease->save();

            return $locked->fresh();
        });
    }

    /** @return array{rou_asset: LedgerAccount, liability: LedgerAccount, interest: LedgerAccount} */
    private function ensureAccounts(): array
    {
        $defs = [
            self::ROU_ASSET => ['Hak Guna Aset Sewa (PSAK 73)', AccountKind::ASSET->value, false],
            self::LEASE_LIABILITY => ['Liabilitas Sewa (PSAK 73)', AccountKind::LIABILITY->value, true],
            self::LEASE_INTEREST => ['Bunga Sewa (PSAK 73)', AccountKind::EXPENSE->value, false],
        ];

        $accounts = [];
        foreach ($defs as $code => [$name, $kind, $allowNegative]) {
            $accounts[$code] = LedgerAccount::firstOrCreate(
                ['code' => $code, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'kind' => $kind,
                    'allow_negative' => $allowNegative,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }

        return [
            'rou_asset' => $accounts[self::ROU_ASSET],
            'liability' => $accounts[self::LEASE_LIABILITY],
            'interest' => $accounts[self::LEASE_INTEREST],
        ];
    }

    private function assets(): AssetService
    {
        return app(AssetService::class);
    }
}
