<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use App\Models\User;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order as RestoOrder;
use Modules\Shared\Contracts\SearchProviderInterface;

class RestoSearchProvider implements SearchProviderInterface
{
    public function search(string $keyword, ?User $user = null): array
    {
        $q = trim($keyword);
        $results = [];

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

        return $results;
    }
}
