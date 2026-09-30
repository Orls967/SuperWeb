<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Queries;

use App\Models\User;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order as RestoOrder;
use Modules\Shared\Application\MenuRegistry;
use Modules\Store\Domain\Models\StoreItem;

class GlobalSearchQuery
{
    public function __construct(
        protected MenuRegistry $menuRegistry,
    ) {}

    public function search(string $keyword, ?User $user = null): array
    {
        $q = trim($keyword);
        if (strlen($q) < 2) {
            return $this->defaultSuggestions($user);
        }

        $results = [];

        // 1. Pencarian Menu Navigasi
        $menuItems = $this->menuRegistry->getItemsForUser($user);
        foreach ($menuItems as $item) {
            if (stripos($item->label, $q) !== false || stripos($item->group ?? '', $q) !== false) {
                $results[] = [
                    'category' => 'Menu Navigasi',
                    'title' => $item->label,
                    'subtitle' => 'Kategori: '.($item->group ?? 'Umum'),
                    'badge' => 'NAVIGASI',
                    'badge_color' => 'bg-slate-700 text-slate-300',
                    'url' => route($item->route),
                ];
            }
        }

        // 2. Otomotif & Bengkel
        try {
            $bookings = Booking::query()
                ->where('booking_code', 'like', "%{$q}%")
                ->orWhere('plate_number', 'like', "%{$q}%")
                ->limit(4)
                ->get();

            foreach ($bookings as $b) {
                $results[] = [
                    'category' => 'Otomotif & Bengkel',
                    'title' => "Booking #{$b->booking_code} ({$b->plate_number})",
                    'subtitle' => "Status: {$b->status->label()} • Biaya: Rp ".number_format((float) $b->grand_total, 0, ',', '.'),
                    'badge' => 'BOOKING',
                    'badge_color' => 'bg-blue-500/20 text-blue-300',
                    'url' => route('bookings.show', $b),
                ];
            }

            $cars = Car::query()
                ->where('name', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%")
                ->limit(3)
                ->get();

            foreach ($cars as $car) {
                $results[] = [
                    'category' => 'Otomotif & Bengkel',
                    'title' => "Mobil: {$car->brand} {$car->name} ({$car->year})",
                    'subtitle' => 'Harga Rp '.number_format((float) $car->price, 0, ',', '.'),
                    'badge' => 'AUTODEX',
                    'badge_color' => 'bg-indigo-500/20 text-indigo-300',
                    'url' => route('autodex.show', $car),
                ];
            }

            $storeItems = StoreItem::query()
                ->where('name', 'like', "%{$q}%")
                ->limit(3)
                ->get();

            foreach ($storeItems as $item) {
                $results[] = [
                    'category' => 'Otomotif & Bengkel',
                    'title' => "Produk Toko: {$item->name}",
                    'subtitle' => 'Rp '.number_format((float) $item->price, 0, ',', '.'),
                    'badge' => 'STORE',
                    'badge_color' => 'bg-amber-500/20 text-amber-300',
                    'url' => route('store.catalog.index', ['search' => $item->name]),
                ];
            }
        } catch (\Throwable) {
        }

        // 3. Kuliner (RM Sari Ranah)
        try {
            $menuItemsResto = MenuItem::query()
                ->where('name', 'like', "%{$q}%")
                ->limit(4)
                ->get();

            foreach ($menuItemsResto as $m) {
                $results[] = [
                    'category' => 'Kuliner Nusantara',
                    'title' => "Hidangan: {$m->name}",
                    'subtitle' => "Kategori: {$m->category?->name} • Harga: Rp ".number_format((int) $m->price, 0, ',', '.'),
                    'badge' => 'RESTO MENU',
                    'badge_color' => 'bg-amber-500/20 text-amber-300',
                    'url' => route('resto.menu.index', ['search' => $m->name]),
                ];
            }

            $restoOrders = RestoOrder::query()
                ->where('number', 'like', "%{$q}%")
                ->limit(3)
                ->get();

            foreach ($restoOrders as $ro) {
                $results[] = [
                    'category' => 'Kuliner Nusantara',
                    'title' => "Pesanan Resto #{$ro->number}",
                    'subtitle' => "Status: {$ro->status->value} • Total: Rp ".number_format((int) $ro->grand_total, 0, ',', '.'),
                    'badge' => 'ORDER',
                    'badge_color' => 'bg-orange-500/20 text-orange-300',
                    'url' => route('resto.pos.order.receipt', $ro),
                ];
            }
        } catch (\Throwable) {
        }

        // 4. Properti Duta Mall
        try {
            $tenants = Tenant::query()
                ->where('brand_name', 'like', "%{$q}%")
                ->orWhere('external_ref', 'like', "%{$q}%")
                ->limit(4)
                ->get();

            foreach ($tenants as $t) {
                $results[] = [
                    'category' => 'Properti Mall',
                    'title' => "Tenant: {$t->brand_name} (".($t->external_ref ?: 'Reguler').')',
                    'subtitle' => "PIC: {$t->pic_name} • Kategori: {$t->category->value}",
                    'badge' => 'TENANT',
                    'badge_color' => 'bg-emerald-500/20 text-emerald-300',
                    'url' => route('mall.tenants.show', $t),
                ];
            }

            $units = Unit::query()
                ->where('unit_number', 'like', "%{$q}%")
                ->limit(3)
                ->get();

            foreach ($units as $u) {
                $results[] = [
                    'category' => 'Properti Mall',
                    'title' => "Unit Toko {$u->unit_number} (Lt. {$u->floor})",
                    'subtitle' => "Luas: {$u->area_sqm} m² • Status: {$u->status->value}",
                    'badge' => 'UNIT',
                    'badge_color' => 'bg-teal-500/20 text-teal-300',
                    'url' => route('mall.units.show', $u),
                ];
            }

            $leases = Lease::query()
                ->where('lease_number', 'like', "%{$q}%")
                ->limit(3)
                ->get();

            foreach ($leases as $l) {
                $results[] = [
                    'category' => 'Properti Mall',
                    'title' => "Kontrak Sewa #{$l->lease_number}",
                    'subtitle' => "Tenant: {$l->tenant?->brand_name} • Status: {$l->status->value}",
                    'badge' => 'LEASE',
                    'badge_color' => 'bg-cyan-500/20 text-cyan-300',
                    'url' => route('mall.leases.show', $l),
                ];
            }

            $parkingSessions = ParkingSession::query()
                ->where('ticket_number', 'like', "%{$q}%")
                ->orWhere('plate_number', 'like', "%{$q}%")
                ->limit(3)
                ->get();

            foreach ($parkingSessions as $ps) {
                $results[] = [
                    'category' => 'Properti Mall',
                    'title' => "Tiket Parkir #{$ps->ticket_number} ({$ps->plate_number})",
                    'subtitle' => "Status: {$ps->status->value} • Masuk: {$ps->entry_time?->format('H:i')}",
                    'badge' => 'PARKIR',
                    'badge_color' => 'bg-violet-500/20 text-violet-300',
                    'url' => route('mall.parking.index'),
                ];
            }
        } catch (\Throwable) {
        }

        return $results;
    }

    private function defaultSuggestions(?User $user): array
    {
        $suggestions = [
            [
                'category' => 'Aksi Cepat',
                'title' => 'Dashboard Grup Konsolidasi',
                'subtitle' => 'P&L 4 Lini Bisnis Real-Time dari Buku Besar',
                'badge' => 'ADMIN',
                'badge_color' => 'bg-indigo-500/20 text-indigo-300',
                'url' => route('admin.group-dashboard'),
            ],
            [
                'category' => 'Aksi Cepat',
                'title' => 'POS Kasir & Meja Hidang',
                'subtitle' => 'Layar Transaksi Kasir RM Sari Ranah',
                'badge' => 'RESTO',
                'badge_color' => 'bg-amber-500/20 text-amber-300',
                'url' => route('resto.pos.index'),
            ],
            [
                'category' => 'Aksi Cepat',
                'title' => 'Gate Simulasi Parkir & Tiket',
                'subtitle' => 'Operasional Barrier Gate Masuk & Keluar Mall',
                'badge' => 'MALL',
                'badge_color' => 'bg-emerald-500/20 text-emerald-300',
                'url' => route('mall.parking.gate.entry'),
            ],
            [
                'category' => 'Aksi Cepat',
                'title' => 'Dompet & Saldo Saya',
                'subtitle' => 'Kelola Saldo IDR, Mutasi & Transfer Instan',
                'badge' => 'WALLET',
                'badge_color' => 'bg-blue-500/20 text-blue-300',
                'url' => route('wallet.index'),
            ],
        ];

        return $suggestions;
    }
}
