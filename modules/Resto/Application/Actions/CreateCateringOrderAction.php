<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Resto\Domain\Enums\CateringStatus;
use Modules\Resto\Domain\Enums\OutletType;
use Modules\Resto\Domain\Exceptions\CateringCapacityExceededException;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Resto\Domain\Models\CateringPackage;
use Modules\Resto\Domain\Models\Outlet;

class CreateCateringOrderAction
{
    public function handle(
        int $outletId,
        string $customerName,
        string $customerPhone,
        string $deliveryAddress,
        string $eventDate,
        int $pax,
        ?int $packageId = null,
        ?User $user = null,
        string $eventTime = '11:00:00',
        ?string $notes = null
    ): CateringOrder {
        $outlet = Outlet::findOrFail($outletId);

        if ($pax <= 0) {
            throw new InvalidArgumentException('Jumlah porsi katering harus lebih besar dari 0.');
        }

        $parsedDate = Carbon::parse($eventDate)->toDateString();

        // 1. Validasi Kapasitas Produksi Dapur Harian Outlet
        $maxDailyPax = $outlet->type === OutletType::CENTRAL_KITCHEN ? 2000 : 500;
        $existingBookedPax = (int) CateringOrder::where('outlet_id', $outlet->id)
            ->whereDate('event_date', $parsedDate)
            ->where('status', '!=', CateringStatus::CANCELLED)
            ->sum('pax');

        if (($existingBookedPax + $pax) > $maxDailyPax) {
            throw new CateringCapacityExceededException(
                "Kapasitas dapur katering {$outlet->name} pada {$parsedDate} telah melebihi batas harian. Tersedia: ".($maxDailyPax - $existingBookedPax)." pax, diminta: {$pax} pax."
            );
        }

        // 2. Kalkulasi Paket & Harga
        $package = $packageId ? CateringPackage::findOrFail($packageId) : null;
        if ($package && $pax < $package->min_pax) {
            throw new InvalidArgumentException("Paket {$package->name} memerlukan pemesanan minimal {$package->min_pax} pax.");
        }

        $pricePerPax = $package ? $package->price_per_pax : 35000;
        $subtotal = $pricePerPax * $pax;
        $deliveryFee = 50000;
        $grandTotal = $subtotal + $deliveryFee;
        $depositAmount = (int) round($grandTotal * 0.30); // 30% deposit

        return DB::transaction(function () use (
            $outlet,
            $user,
            $package,
            $customerName,
            $customerPhone,
            $deliveryAddress,
            $parsedDate,
            $eventTime,
            $pax,
            $subtotal,
            $deliveryFee,
            $grandTotal,
            $depositAmount,
            $notes
        ) {
            $dateStr = date('Ymd');
            $randomCode = strtoupper(Str::random(4));
            $number = "CAT-RSR-{$outlet->code}-{$dateStr}-{$randomCode}";

            return CateringOrder::create([
                'uuid' => (string) Str::uuid(),
                'number' => $number,
                'outlet_id' => $outlet->id,
                'user_id' => $user?->id,
                'package_id' => $package?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'event_date' => $parsedDate,
                'event_time' => $eventTime,
                'delivery_address' => $deliveryAddress,
                'pax' => $pax,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'grand_total' => $grandTotal,
                'deposit_amount' => $depositAmount,
                'paid_amount' => 0,
                'status' => CateringStatus::QUOTED,
                'notes' => $notes,
            ]);
        });
    }
}
