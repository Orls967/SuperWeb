<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hotel\Domain\Adapters\SmartLockSimulationAdapter;
use Modules\Hotel\Domain\Models\HotelApoaReport;
use Modules\Hotel\Domain\Models\HotelDigitalKey;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Services\HotelExternalFactors;
use Modules\Hotel\Domain\StateMachines\ApoaReportStateMachine;
use Modules\Hotel\Domain\StateMachines\DigitalKeyStateMachine;
use Modules\Hotel\Domain\StateMachines\ReservationStateMachine;

/**
 * Check-In Tamu dengan Penerbitan Kunci Digital & Antrean Pelaporan APOA WNA (D91-3)
 */
final class CheckInWithDigitalKeyAndApoaAction
{
    /**
     * @param  string|null  $guestNationality  (ID, US, SG, AU, dll)
     * @return array{digital_key: HotelDigitalKey, apoa_report: ?HotelApoaReport, room_number: string}
     */
    public function execute(
        string $reservationId,
        string $roomId,
        ?string $guestNationality = 'ID',
        ?string $passportNumber = null,
        bool $isBaliPwaPaid = false,
        ?string $idempotencyKey = null
    ): array {
        return DB::transaction(function () use (
            $reservationId,
            $roomId,
            $guestNationality,
            $passportNumber,
            $isBaliPwaPaid
        ): array {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);
            $room = HotelRoom::lockForUpdate()->findOrFail($roomId);
            $property = HotelProperty::findOrFail($reservation->property_id);

            // Validasi transisi status
            ReservationStateMachine::assertCanTransition($reservation->status, ReservationStateMachine::STATUS_CHECKED_IN);

            $reservation->update([
                'status' => ReservationStateMachine::STATUS_CHECKED_IN,
                'room_id' => $room->id,
            ]);

            $room->update(['status' => 'occupied']);

            $checkOutTime = CarbonImmutable::parse($reservation->check_out_date)->setTime(12, 0, 0);

            // Terbitkan Kunci Digital Kamar
            $keyData = SmartLockSimulationAdapter::generateDigitalSessionKey(
                (int) abs(crc32($reservation->id)),
                (int) abs(crc32($room->id)),
                $checkOutTime
            );

            $userId = (int) ($reservation->guest_user_id ?? 1);
            if ($userId <= 0) {
                $userId = 1;
            }

            $digitalKey = HotelDigitalKey::create([
                'key_uuid' => 'KEY-'.bin2hex(random_bytes(8)),
                'reservation_id' => $reservation->id,
                'room_id' => $room->id,
                'guest_user_id' => $userId,
                'session_token_hash' => $keyData['session_token_hash'],
                'qr_payload_encrypted' => $keyData['qr_payload_encrypted'],
                'valid_from' => CarbonImmutable::now(),
                'valid_until' => $checkOutTime,
                'status' => DigitalKeyStateMachine::STATUS_ACTIVE,
            ]);

            // Jika Tamu WNA -> Buat Antrean Pelaporan APOA (UU 6/2011)
            $apoaReport = null;
            if ($guestNationality !== null && strtoupper($guestNationality) !== 'ID') {
                if ($passportNumber === null || trim($passportNumber) === '') {
                    throw new InvalidArgumentException('Nomor paspor wajib diisi untuk tamu WNA.');
                }

                $passportBlindIndex = hash('sha256', strtoupper(trim($passportNumber)));
                $deadlineHours = HotelExternalFactors::getApoaDeadlineHours();

                $apoaReport = HotelApoaReport::create([
                    'reservation_id' => $reservation->id,
                    'property_id' => $property->id,
                    'guest_user_id' => $userId,
                    'nationality_country_code' => strtoupper($guestNationality),
                    'passport_blind_index' => $passportBlindIndex,
                    'passport_encrypted' => $passportNumber,
                    'visa_type' => 'B211A',
                    'bali_pwa_levy_verified' => $isBaliPwaPaid,
                    'deadline_at' => CarbonImmutable::now()->addHours($deadlineHours),
                    'status' => ApoaReportStateMachine::STATUS_PENDING,
                ]);
            }

            return [
                'digital_key' => $digitalKey,
                'apoa_report' => $apoaReport,
                'room_number' => (string) ($room->room_number ?? $room->id),
            ];
        });
    }
}
