<?php

declare(strict_types=1);

namespace Modules\Resto\Console\Commands;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Console\Command;
use Modules\Core\Application\Services\NotificationService;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Resto\Application\Actions\CreatePurchaseOrderAction;
use Modules\Resto\Domain\Enums\POStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\RestoStaffAssignment;
use Modules\Resto\Domain\Models\Supplier;

class MonitorStockLevelsCommand extends Command
{
    protected $signature = 'resto:check-stock';

    protected $description = 'Pantau level stok minimum bahan baku, buat saran PO draft, dan kirim peringatan bahan kedaluwarsa';

    public function handle(
        InventoryService $inventoryService,
        CreatePurchaseOrderAction $createPoAction,
        NotificationService $notificationService
    ): int {
        $this->info('Memeriksa stok minimum bahan baku dan batas kedaluwarsa...');

        $outlets = Outlet::where('is_active', true)->get();
        $defaultSupplier = Supplier::where('is_active', true)->first() ?? Supplier::create([
            'name' => 'Supplier Utama Minang Bersama',
            'contact' => '081234567890',
            'terms_days' => 30,
            'is_active' => true,
            'rating' => 5,
        ]);

        $systemUser = User::where('role', 'admin')->first() ?? User::first();

        foreach ($outlets as $outlet) {
            $lowStockItems = [];
            $ingredients = Ingredient::all()->filter(fn ($i) => (float) $i->min_stock_base_unit > 0);

            foreach ($ingredients as $ing) {
                $available = BigDecimal::of((string) $inventoryService->availableIngredient($ing->id, $outlet->id));
                $minStock = BigDecimal::of((string) $ing->min_stock_base_unit);

                if ($available->isLessThan($minStock)) {
                    $neededQty = $minStock->multipliedBy(BigDecimal::of('2'))->minus($available); // Target replenish
                    $lowStockItems[] = [
                        'ingredient_id' => $ing->id,
                        'name' => $ing->name,
                        'available' => $available->__toString(),
                        'min_stock' => $minStock->__toString(),
                        'qty' => $neededQty->toScale(2)->__toString(),
                        'unit' => $ing->base_unit->value,
                        'unit_price' => 20_000,
                    ];
                }
            }

            if (! empty($lowStockItems)) {
                $this->warn("Outlet {$outlet->name}: Ditemukan ".count($lowStockItems).' bahan di bawah stok minimum.');

                // Check if there is already a draft PO today for this outlet
                $existingDraft = PurchaseOrder::where('outlet_id', $outlet->id)
                    ->where('status', POStatus::DRAFT)
                    ->whereDate('created_at', now()->toDateString())
                    ->first();

                if (! $existingDraft && $systemUser) {
                    $po = $createPoAction->handle(
                        outletId: $outlet->id,
                        supplierId: $defaultSupplier->id,
                        linesData: $lowStockItems,
                        creator: $systemUser,
                        autoSend: false
                    );
                    $this->info("  -> Saran PO otomatis dibuat: #{$po->number} (Status: DRAFT)");
                }

                // Notify outlet manager
                $managerAssignment = RestoStaffAssignment::where('outlet_id', $outlet->id)
                    ->where('role', 'outlet_manager')
                    ->where('is_active', true)
                    ->first();

                if ($managerAssignment) {
                    $notificationService->send(
                        userId: $managerAssignment->user_id,
                        type: 'resto_low_stock',
                        title: "Peringatan Stok Rendah: {$outlet->name}",
                        body: count($lowStockItems).' bahan baku berada di bawah batas minimum. Draf saran PO telah disiapkan.',
                        actionUrl: route('resto.purchases.index')
                    );
                }
            }

            // Monitor display trays nearing expiry (< 2 hours)
            $nearExpiryTrays = DisplayTray::where('outlet_id', $outlet->id)
                ->whereIn('status', [TrayStatus::ON_DISPLAY, TrayStatus::IN_SERVICE])
                ->where('portions_remaining', '>', 0)
                ->where('expires_at', '<=', now()->addHours(2))
                ->where('expires_at', '>', now())
                ->with('menuItem')
                ->get();

            if ($nearExpiryTrays->isNotEmpty()) {
                $this->warn("Outlet {$outlet->name}: Ditemukan {$nearExpiryTrays->count()} piring etalase mendekati kedaluwarsa (< 2 jam).");

                $kitchenStaff = RestoStaffAssignment::where('outlet_id', $outlet->id)
                    ->where('role', 'kitchen')
                    ->where('is_active', true)
                    ->get();

                foreach ($kitchenStaff as $staff) {
                    $notificationService->send(
                        userId: $staff->user_id,
                        type: 'resto_near_expiry',
                        title: 'Peringatan Makanan Etalase',
                        body: "Terdapat {$nearExpiryTrays->count()} piring di etalase yang akan kedaluwarsa dalam waktu kurang dari 2 jam.",
                        actionUrl: route('resto.kitchen.index')
                    );
                }
            }
        }

        $this->info('✓ Pengecekan stok dan kedaluwarsa selesai.');

        return self::SUCCESS;
    }
}
