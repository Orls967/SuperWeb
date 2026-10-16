<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Queries;

use App\Models\User;
use Modules\Shared\Application\MenuRegistry;
use Modules\Shared\Application\SearchRegistry;

class GlobalSearchQuery
{
    public function __construct(
        protected MenuRegistry $menuRegistry,
        protected ?SearchRegistry $searchRegistry = null,
    ) {}

    protected function getSearchRegistry(): SearchRegistry
    {
        return $this->searchRegistry ?? app(SearchRegistry::class);
    }

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

        // 2. Pencarian Lintas Modul via SearchRegistry
        try {
            $moduleResults = $this->getSearchRegistry()->search($keyword, $user);
            foreach ($moduleResults as $res) {
                $results[] = $res;
            }
        } catch (\Throwable) {
        }

        return $results;
    }

    private function defaultSuggestions(?User $user): array
    {
        return [
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
    }
}
