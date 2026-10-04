<?php

declare(strict_types=1);

namespace Modules\Procurement\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Domain\Models\BudgetCenter;
use Modules\Procurement\Domain\Models\Requisition;
use Modules\Supplier\Domain\Models\Supplier;

class ProcurementSeeder extends Seeder
{
    public function run(ProcurementService $service): void
    {
        if (Requisition::count() > 0) {
            return; // idempoten
        }

        $service->createBudgetCenter('SUPPLY-CHAIN', 'Anggaran Pengadaan Umum', 500_000_000);
        $service->createBudgetCenter('FACILITY', 'Anggaran Fasilitas & Utilitas', 200_000_000);

        $center = BudgetCenter::where('code', 'SUPPLY-CHAIN')->firstOrFail();
        $admin = User::where('role', 'admin')->first()
            ?? User::first();

        if ($admin === null) {
            return;
        }

        // PR contoh → langsung disetujui (memegang encumbrance).
        $pr = $service->createRequisition([
            'title' => 'Pengadaan bahan baku kuartal berjalan',
            'source' => 'reorder_point',
            'budget_center_id' => $center->id,
            'lines' => [
                ['description' => 'Pangan beku (pallet)', 'qty' => 40, 'estimated_unit_price_idr' => 2_500_000],
                ['description' => 'Kemasan kardus laminasi', 'qty' => 500, 'estimated_unit_price_idr' => 12_000],
            ],
        ], $admin, true); // diajukan approval → approveRequisition mengunci anggaran

        $service->approveRequisition($pr);

        // RFQ contoh dengan pemasok aktif (jika ada).
        $suppliers = Supplier::where('is_active', true)
            ->whereIn('status', ['approved', 'preferred'])
            ->pluck('id')
            ->take(3);

        if ($suppliers->isNotEmpty()) {
            $service->createRfq([
                'title' => 'RFQ bahan baku kuartal berjalan',
                'requisition_id' => $pr->id,
                'closes_at' => now()->addDays(5),
            ], $suppliers->all(), $admin);
        }
    }
}
