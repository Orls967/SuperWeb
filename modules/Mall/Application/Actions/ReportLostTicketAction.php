<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;
use Modules\Mall\Domain\Exceptions\TicketAlreadySettledException;
use Modules\Mall\Domain\Models\ParkingSession;

/**
 * Pelanggan kehilangan tiket: sesi ditandai tiket hilang sehingga tarif keluar
 * ditambah denda flat, lalu dihitung ulang lewat CheckOutVehicleAction.
 */
class ReportLostTicketAction
{
    public function __construct(
        protected CheckOutVehicleAction $checkOutAction,
    ) {}

    /**
     * @throws InvalidParkingTicketException
     * @throws TicketAlreadySettledException
     */
    public function execute(
        int $propertyId,
        string $plateNumber,
        string $exitGate = 'Gate Keluar 1',
        ?Carbon $exitTime = null
    ): ParkingSession {
        $normalizedPlate = CheckInVehicleAction::normalizePlate($plateNumber);

        $session = DB::transaction(function () use ($propertyId, $normalizedPlate) {
            $session = ParkingSession::query()
                ->lockForUpdate()
                ->where('property_id', $propertyId)
                ->where('plate_number', $normalizedPlate)
                ->where('status', ParkingSessionStatus::ACTIVE)
                ->first();

            if ($session === null) {
                throw new InvalidParkingTicketException(
                    "Tidak ada sesi parkir aktif untuk plat {$normalizedPlate}. Periksa kembali nomor kendaraan."
                );
            }

            $session->is_lost_ticket = true;
            $session->save();

            return $session;
        }, attempts: 3);

        return $this->checkOutAction->execute($session, $exitGate, $exitTime);
    }
}
