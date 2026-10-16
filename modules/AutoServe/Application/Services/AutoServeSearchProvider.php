<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Services;

use App\Models\User;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Shared\Contracts\SearchProviderInterface;

class AutoServeSearchProvider implements SearchProviderInterface
{
    public function search(string $keyword, ?User $user = null): array
    {
        $q = trim($keyword);
        $results = [];

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

        return $results;
    }
}
