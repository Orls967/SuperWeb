<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Mall\Application\Services\ParkingTariffCalculator;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;
use Modules\Mall\Domain\Enums\ParkingPaymentStatus;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Exceptions\TicketAlreadySettledException;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\ParkingTariff;

class CheckOutVehicleAction
{
    public function __construct(
        protected ParkingTariffCalculator $calculator,
    ) {}

    /**
     * Hitung tarif keluar untuk sebuah tiket tanpa mengubah data (untuk layar gate keluar).
     *
     * @return array{
     *     duration_minutes: int,
     *     billed_hours: int,
     *     base_fee: int,
     *     penalty_fee: int,
     *     discount_amount: int,
     *     total_fee: int,
     *     is_free: bool
     * }
     */
    public function quote(ParkingSession $session, ?Carbon $exitTime = null): array
    {
        $exitTime = $exitTime ?? Carbon::now();
        $tariff = $this->resolveTariff($session);
        $durationMinutes = $session->calculateDurationMinutes($exitTime);

        // Potongan validasi tenant dinyatakan dalam jam gratis, nominalnya baru
        // bisa dihitung di sini karena durasi final baru diketahui saat keluar.
        $discount = $session->validation_free_hours > 0
            ? $this->calculator->discountForFreeHours($tariff, $durationMinutes, $session->validation_free_hours)
            : 0;

        return $this->calculator->calculate(
            tariff: $tariff,
            durationMinutes: $durationMinutes,
            isLostTicket: $session->is_lost_ticket,
            member: $session->member,
            discountAmount: $discount,
        );
    }

    /**
     * Finalisasi tarif keluar. Tiket yang tarifnya nol langsung selesai (member/grace/validasi penuh);
     * sisanya menunggu pembayaran lewat SettleParkingSessionAction.
     *
     * @throws TicketAlreadySettledException
     */
    public function execute(ParkingSession $session, string $exitGate = 'Gate Keluar 1', ?Carbon $exitTime = null): ParkingSession
    {
        $exitTime = $exitTime ?? Carbon::now();

        return DB::transaction(function () use ($session, $exitGate, $exitTime) {
            /** @var ParkingSession $locked */
            $locked = ParkingSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($locked->status === ParkingSessionStatus::COMPLETED) {
                throw new TicketAlreadySettledException(
                    "Tiket {$locked->ticket_number} sudah diselesaikan pada {$locked->exit_time?->format('d/m/Y H:i')} dan tidak bisa keluar dua kali."
                );
            }

            $quote = $this->quote($locked, $exitTime);
            $isMemberFree = $locked->member !== null && $locked->member->isValid() && ! $locked->is_lost_ticket;

            $locked->exit_gate = $exitGate;
            $locked->exit_time = $exitTime;
            $locked->duration_minutes = $quote['duration_minutes'];
            $locked->base_fee = $quote['base_fee'];
            $locked->penalty_fee = $quote['penalty_fee'];
            $locked->discount_amount = $quote['discount_amount'];
            $locked->total_fee = $quote['total_fee'];

            if ($quote['total_fee'] === 0) {
                // Tidak ada uang berpindah: tidak ada posting ledger yang dibuat.
                $locked->status = ParkingSessionStatus::COMPLETED;
                $locked->payment_status = ParkingPaymentStatus::WAIVED;
                $locked->payment_method = $isMemberFree
                    ? ParkingPaymentMethod::MEMBER_FREE
                    : ($locked->validated_by_tenant_id !== null ? ParkingPaymentMethod::TENANT_FREE : null);
                $locked->paid_at = $exitTime;

                $this->releaseSlot($locked);
            }

            $locked->save();

            return $locked->fresh(['zone', 'member', 'validatedByTenant']);
        }, attempts: 3);
    }

    /**
     * Kurangi occupancy zona saat kendaraan benar-benar keluar.
     */
    public function releaseSlot(ParkingSession $session): void
    {
        $zone = $session->zone;

        if ($zone !== null && $zone->current_occupancy > 0) {
            $zone->decrement('current_occupancy');
        }
    }

    public function resolveTariff(ParkingSession $session): ParkingTariff
    {
        return ParkingTariff::query()
            ->where('property_id', $session->property_id)
            ->where('vehicle_type', $session->vehicle_type)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
