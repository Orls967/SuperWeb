<?php

declare(strict_types=1);

namespace Modules\Asset\Application\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Asset\Domain\Enums\AssetEventType;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetUsageLog;
use Modules\Asset\Domain\Models\AssetWorkOrder;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Contracts\DocumentNumberingInterface;

/**
 * 31.5 Pemeliharaan aset (work order) berbasis waktu/pemakaian.
 *
 * Biaya WO masuk ledger: `expense` → `clearing:external:IDR`, atau
 * `capitalized` → debit `ast:fixed_assets` (menambah biaya aset berjalan,
 * lalu book value dihitung ulang).
 */
class WorkOrderService
{
    public const EXPENSE_ACCOUNT = 'maintenance:asset:IDR';

    public function __construct(
        private readonly DocumentNumberingInterface $numbering,
        private readonly Ledger $ledger,
        private readonly AssetService $assets,
    ) {}

    /**
     * Buat work order (nomor gapless, trigger time/usage).
     *
     * @param array{type?: string, trigger?: string, due_date?: string, due_units?: int,
     *   parts_cost_idr?: int, labor_cost_idr?: int, cost_treatment?: string,
     *   description?: string, vendor?: string, assigned_to_user_id?: int} $data
     */
    public function schedule(Asset $asset, array $data = []): AssetWorkOrder
    {
        return DB::transaction(function () use ($asset, $data) {
            $number = $this->numbering->nextNumber(
                entityCode: 'AST',
                documentType: 'WO',
                resetMonthly: false,
                customPrefix: 'WO/{ENT}/',
            );

            $parts = (int) ($data['parts_cost_idr'] ?? 0);
            $labor = (int) ($data['labor_cost_idr'] ?? 0);

            $wo = AssetWorkOrder::create([
                'asset_id' => $asset->id,
                'number' => $number,
                'type' => $data['type'] ?? 'preventive',
                'trigger' => $data['trigger'] ?? 'time',
                'status' => 'scheduled',
                'due_date' => $data['due_date'] ?? null,
                'due_units' => $data['due_units'] ?? null,
                'parts_cost_idr' => $parts,
                'labor_cost_idr' => $labor,
                'total_cost_idr' => $parts + $labor,
                'cost_treatment' => $data['cost_treatment'] ?? 'expense',
                'description' => $data['description'] ?? null,
                'vendor' => $data['vendor'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            ]);

            $this->assets->recordEvent($asset, AssetEventType::Maintenance, [
                'action' => 'scheduled', 'wo_number' => $number, 'trigger' => $wo->trigger,
                'due_date' => $wo->due_date?->toDateString(), 'due_units' => $wo->due_units,
                'cost_treatment' => $wo->cost_treatment,
            ]);

            return $wo;
        });
    }

    /**
     * Selesaikan work order: posting biaya (expense/capitalized) + event rantai.
     *
     * Idempoten: WO sudah `completed` → kembali tanpa posting ganda.
     */
    public function complete(
        AssetWorkOrder $workOrder,
        ?int $completedUnits = null,
        ?int $partsCostIdr = null,
        ?int $laborCostIdr = null
    ): AssetWorkOrder {
        return DB::transaction(function () use ($workOrder, $completedUnits, $partsCostIdr, $laborCostIdr) {
            /** @var AssetWorkOrder $locked */
            $locked = AssetWorkOrder::query()->lockForUpdate()->findOrFail($workOrder->getKey());

            if ($locked->status === 'completed') {
                return $locked;
            }

            if (in_array($locked->status, ['scheduled', 'in_progress'], true) === false) {
                throw new InvalidArgumentException("WO {$locked->number} sudah berstatus {$locked->status}.");
            }

            /** @var Asset $asset */
            $asset = Asset::query()->with('category')->lockForUpdate()->findOrFail($locked->asset_id);

            $parts = $partsCostIdr ?? (int) $locked->parts_cost_idr;
            $labor = $laborCostIdr ?? (int) $locked->labor_cost_idr;
            $total = $parts + $labor;

            if ($total > 0) {
                $tx = $this->postCost($asset, $locked, $total);
                $locked->ledger_transaction_id = $tx->id;
            }

            // Capitalized: tambahkan ke biaya aset (biaya perolehan) lalu hitung ulang book value.
            if ($locked->cost_treatment === 'capitalized' && $total > 0) {
                $asset->landed_cost_idr = (int) $asset->landed_cost_idr + $total;
                $asset->book_value_idr = $asset->recomputeBookValue();
                $asset->save();
            }

            $locked->parts_cost_idr = $parts;
            $locked->labor_cost_idr = $labor;
            $locked->total_cost_idr = $total;
            $locked->status = 'completed';
            $locked->completed_at = now();
            $locked->completed_units = $completedUnits;
            $locked->save();

            $this->assets->recordEvent($asset, AssetEventType::Maintenance, [
                'action' => 'completed', 'wo_number' => $locked->number,
                'total_cost_idr' => $total, 'cost_treatment' => $locked->cost_treatment,
                'capitalized' => $locked->cost_treatment === 'capitalized',
            ], $locked->assigned_to_user_id ? (string) $locked->assigned_to_user_id : 'system');

            return $locked->fresh();
        });
    }

    /**
     * WO yang jatuh tempo: berdasarkan tanggal (time) atau pemakaian (usage).
     *
     * @return array<int, AssetWorkOrder>
     */
    public function dueWorkOrders(): array
    {
        $today = now()->toDateString();

        $timeDue = AssetWorkOrder::query()
            ->with('asset')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('trigger', 'time')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today)
            ->get();

        $usageDue = [];
        foreach (AssetWorkOrder::query()
            ->with('asset')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('trigger', 'usage')
            ->whereNotNull('due_units')
            ->get() as $wo) {
            $currentUnits = (int) AssetUsageLog::query()
                ->where('asset_id', $wo->asset_id)
                ->max('units');

            if ($currentUnits >= (int) $wo->due_units) {
                $usageDue[] = $wo;
            }
        }

        return $timeDue->concat($usageDue)->values()->all();
    }

    private function postCost(Asset $asset, AssetWorkOrder $wo, int $total): LedgerTransaction
    {
        $expenseCode = $wo->cost_treatment === 'capitalized'
            ? AssetService::ACCT_FIXED_ASSETS
            : self::EXPENSE_ACCOUNT;

        // Pastikan akun aset tetap ada sebelum posting (pembuatan lazily).
        if ($wo->cost_treatment === 'capitalized') {
            $this->assets->ensureAccounts();
        }

        LedgerAccount::firstOrCreate(
            ['code' => self::EXPENSE_ACCOUNT, 'asset_code' => 'IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Beban Pemeliharaan Aset',
                'kind' => AccountKind::EXPENSE->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        return $this->ledger->post(new PostingDTO(
            type: TransactionType::ASSET_WORK_ORDER->value,
            description: "Biaya pemeliharaan {$wo->number} — {$asset->asset_number}",
            idempotencyKey: 'ast:wo:'.$wo->id,
            entries: [
                PostingEntryDTO::forCode($expenseCode, 'IDR', BigDecimal::of($total)),
                PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', BigDecimal::of($total)->negated()),
            ],
            referenceType: AssetWorkOrder::class,
            referenceId: $wo->id,
            meta: [
                'wo_number' => $wo->number,
                'asset_id' => $asset->id,
                'cost_treatment' => $wo->cost_treatment,
                'parts' => $wo->parts_cost_idr,
                'labor' => $wo->labor_cost_idr,
            ],
            createdBy: $wo->assigned_to_user_id,
            postedAt: now(),
        ));
    }
}
