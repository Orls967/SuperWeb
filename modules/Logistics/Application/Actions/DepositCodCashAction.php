<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Exceptions\CodException;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;

class DepositCodCashAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger
    ) {}

    /**
     * Alur 7: driver menyetor seluruh uang COD yang dipegangnya ke hub. Jumlah fisik harus sama persis
     * dengan total pengumpulan yang belum disetor; selisih ditolak dan tidak ada yang diposting.
     *
     * @return array{collections: int, amount: int}
     */
    public function execute(User $receiver, Location $hub, Driver $driver, int $physicalAmountIdr): array
    {
        if (! $receiver->isAdmin() && ! $receiver->isLogisticsAdmin() && (int) $receiver->assignedHubId() !== $hub->id) {
            throw CodException::wrongHub();
        }

        return DB::transaction(function () use ($receiver, $hub, $driver, $physicalAmountIdr) {
            $rows = CodCollection::where('driver_id', $driver->id)
                ->where('status', CodCollection::STATUS_COLLECTED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw CodException::nothingToDeposit();
            }

            $expected = (int) $rows->sum('amount_idr');
            if ($physicalAmountIdr !== $expected) {
                throw CodException::depositMismatch($expected, $physicalAmountIdr);
            }

            $this->ledger->post(
                type: TransactionType::LOGISTICS_COD_DEPOSIT,
                description: "Setoran COD driver {$driver->driver_number} di hub {$hub->code} ({$rows->count()} resi)",
                idempotencyKey: 'lgx:cod_deposit:'.$driver->id.':'.md5($rows->pluck('id')->implode(',')),
                entries: [
                    [LogisticsLedger::hubCashCode($hub->id), -$expected],
                    [LogisticsLedger::driverCashCode($driver->id), $expected],
                ],
                referenceType: Driver::class,
                referenceId: $driver->id,
                createdBy: $receiver->id,
            );

            CodCollection::whereIn('id', $rows->pluck('id'))->update([
                'status' => CodCollection::STATUS_DEPOSITED,
                'deposit_hub_id' => $hub->id,
                'deposited_by' => $receiver->id,
                'deposited_at' => now(),
            ]);

            return ['collections' => $rows->count(), 'amount' => $expected];
        });
    }
}
