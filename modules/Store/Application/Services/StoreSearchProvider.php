<?php

declare(strict_types=1);

namespace Modules\Store\Application\Services;

use App\Models\User;
use Modules\Shared\Contracts\SearchProviderInterface;
use Modules\Store\Domain\Models\StoreItem;

class StoreSearchProvider implements SearchProviderInterface
{
    public function search(string $keyword, ?User $user = null): array
    {
        $q = trim($keyword);
        $results = [];

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

        return $results;
    }
}
