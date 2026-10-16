<?php

declare(strict_types=1);

namespace Modules\AutoDex\Application\Services;

use App\Models\User;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Shared\Contracts\SearchProviderInterface;

class AutoDexSearchProvider implements SearchProviderInterface
{
    public function search(string $keyword, ?User $user = null): array
    {
        $q = trim($keyword);
        $results = [];

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

        return $results;
    }
}
