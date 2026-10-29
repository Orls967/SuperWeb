<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\OutpatientDispensing;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Ledger\HospitalTransactionTypes;

class DispensePrescriptionFefoAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return list<OutpatientDispensing>
     */
    public function execute(OutpatientPrescription $prescription, int $branchId, ?User $pharmacist = null): array
    {
        if ($pharmacist !== null && ! in_array($pharmacist->role, ['pharmacist', 'admin'], true)) {
            throw new AuthorizationException('Hanya apoteker yang berwenang melakukan dispensing obat.');
        }

        if ($prescription->status === 'DISPENSED') {
            throw new DomainException('Resep ini sudah pernah diserahkan.');
        }

        $today = now()->toDateString();

        return DB::transaction(function () use ($prescription, $branchId, $pharmacist, $today) {
            // Ambil seluruh lot aktif, tidak dikarantina, belum kedaluwarsa, urut FEFO (expiry_date ASC)
            $availableLots = PharmacyBranchLot::where('branch_id', $branchId)
                ->where('drug_code', $prescription->drug_code)
                ->where('is_quarantined', false)
                ->whereDate('expiry_date', '>=', $today)
                ->where('quantity', '>', 0)
                ->orderBy('expiry_date', 'asc')
                ->lockForUpdate()
                ->get();

            $totalStock = $availableLots->sum('quantity');
            if ($totalStock < $prescription->quantity) {
                throw new DomainException("Total stok obat {$prescription->drug_name} tidak mencukupi di gudang farmasi (Tersedia: {$totalStock}, Dibutuhkan: {$prescription->quantity}).");
            }

            $remainingNeeded = $prescription->quantity;
            $dispensings = [];
            $totalCogsIdr = 0;

            foreach ($availableLots as $lot) {
                if ($remainingNeeded <= 0) {
                    break;
                }

                $takeQty = min($lot->quantity, $remainingNeeded);
                $lot->decrement('quantity', $takeQty);

                $unitCost = (int) ($lot->unit_cost_idr ?? 1000);
                $cogsForLot = $takeQty * $unitCost;
                $totalCogsIdr += $cogsForLot;

                $dispenseCode = sprintf('DSP-%s-%04d', now()->format('Ymd'), (OutpatientDispensing::count() + 1 + count($dispensings)));

                $dispensing = OutpatientDispensing::create([
                    'dispensing_code' => $dispenseCode,
                    'prescription_id' => $prescription->id,
                    'lot_id' => $lot->id,
                    'quantity' => $takeQty,
                    'unit_cost_idr' => $unitCost,
                    'total_cogs_idr' => $cogsForLot,
                    'dispensed_by_user_id' => $pharmacist?->id ?? 1,
                    'dispensed_at' => now(),
                ]);

                $dispensings[] = $dispensing;
                $remainingNeeded -= $takeQty;
            }

            $prescription->update(['status' => 'DISPENSED']);
            if ($prescription->encounter) {
                $prescription->encounter->update(['status' => 'DISPENSED']);
            }

            // Posting ledger double-entry pengakuan HPP obat
            if ($totalCogsIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: HospitalTransactionTypes::HSP_OUTPATIENT_COGS->value,
                    description: "Pengakuan beban HPP obat resep {$prescription->prescription_code}",
                    idempotencyKey: "HSP-COGS-{$prescription->id}",
                    entries: Posting::lines()
                        ->debit('hsp:cogs_pharmacy:IDR', $totalCogsIdr)
                        ->credit('hsp:inventory_pharmacy:IDR', $totalCogsIdr)
                        ->toEntries('IDR'),
                    referenceType: 'OUTPATIENT_PRESCRIPTION',
                    referenceId: (string) $prescription->id,
                ));
            }

            return $dispensings;
        });
    }
}
