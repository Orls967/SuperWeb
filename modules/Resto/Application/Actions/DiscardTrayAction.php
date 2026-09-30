<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Resto\Domain\Enums\BatchStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;

class DiscardTrayAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function handle(DisplayTray $tray, string $reason, ?int $userId = null): DisplayTray
    {
        if ($tray->status === TrayStatus::DISCARDED) {
            return $tray;
        }

        return DB::transaction(function () use ($tray, $reason, $userId) {
            $portionsToDiscard = $tray->portions_remaining;
            $wasteValue = $tray->totalWasteValue();

            // Post to ledger if waste value > 0
            if ($wasteValue > 0) {
                $outlet = $tray->outlet;
                $outletCode = $outlet?->code ?: "OUT-{$tray->outlet_id}";
                $invAccCode = "inventory:resto:{$outletCode}:IDR";
                $wasteAccCode = 'expense:resto:waste:IDR';

                $this->ensureLedgerAccountExists($invAccCode, "Persediaan Resto {$outlet?->name}", AccountKind::INVENTORY);
                $this->ensureLedgerAccountExists($wasteAccCode, 'Beban Limbah/Waste Makanan Resto', AccountKind::EXPENSE);

                $wasteBd = BigDecimal::of($wasteValue);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::WASTE->value,
                    description: "Waste piring etalase #{$tray->id} ({$tray->menuItem?->name}): {$portionsToDiscard} porsi. Alasan: {$reason}",
                    idempotencyKey: "resto:tray:waste:{$tray->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($invAccCode, 'IDR', $wasteBd->negated()),
                        PostingEntryDTO::forCode($wasteAccCode, 'IDR', $wasteBd),
                    ],
                    referenceType: 'resto_tray',
                    referenceId: $tray->id,
                    createdBy: $userId
                ));
            }

            $tray->status = TrayStatus::DISCARDED;
            $tray->portions_remaining = 0;
            $tray->save();

            // If batch has no more active trays or remaining portions, mark batch discarded/depleted
            $batch = $tray->batch;
            if ($batch) {
                $hasActiveTrays = $batch->trays()
                    ->whereIn('status', [TrayStatus::ON_DISPLAY, TrayStatus::IN_SERVICE, TrayStatus::RETURNED])
                    ->where('portions_remaining', '>', 0)
                    ->exists();

                if (! $hasActiveTrays && $batch->status === BatchStatus::ON_DISPLAY) {
                    $batch->status = BatchStatus::DISCARDED;
                    $batch->save();
                }
            }

            return $tray;
        }, attempts: 3);
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
