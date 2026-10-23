<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

use App\Models\User;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Modules\Shared\Contracts\SearchProviderInterface;

class MallSearchProvider implements SearchProviderInterface
{
    public function search(string $keyword, ?User $user = null): array
    {
        $q = trim($keyword);
        $results = [];

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
                'badge' => 'PARKING',
                'badge_color' => 'bg-blue-500/20 text-blue-300',
                'url' => route('mall.parking.index', ['search' => $ps->ticket_number]),
            ];
        }

        return $results;
    }
}
