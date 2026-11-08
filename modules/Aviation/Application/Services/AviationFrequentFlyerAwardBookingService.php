<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;
use Modules\Aviation\Domain\Models\AviationTicket;

class AviationFrequentFlyerAwardBookingService
{
    public function __construct(
        protected AviationLoyaltyProgramService $loyaltyService,
        protected AviationInterlineTaxSettlementService $taxService
    ) {}

    /**
     * Pesan tiket penerbangan menggunakan miles frequent flyer (Award Ticket Redemption).
     *
     * @param  string  $cabinClass  'economy' atau 'business'
     * @return array{
     *     ticket_id: int,
     *     ticket_number: string,
     *     miles_redeemed: int,
     *     taxes_and_fees_idr: int,
     *     booking_status: string
     * }
     */
    public function bookAwardFlight(
        int $loyaltyAccountId,
        int $flightId,
        string $cabinClass,
        string $passengerName
    ): array {
        return DB::transaction(function () use ($loyaltyAccountId, $flightId, $cabinClass, $passengerName) {
            $account = AviationLoyaltyAccount::lockForUpdate()->findOrFail($loyaltyAccountId);
            $flight = AviationFlight::with(['departureAirport', 'arrivalAirport'])->lockForUpdate()->findOrFail($flightId);

            $isBusiness = strtolower($cabinClass) === 'business';
            $requiredMiles = $isBusiness ? 35000 : 15000;

            if ($account->redeemable_miles < $requiredMiles) {
                throw new DomainException(sprintf(
                    'Saldo miles [%d] tidak mencukupi untuk penerbitan tiket award [%s] yang membutuhkan [%d] miles.',
                    $account->redeemable_miles,
                    strtoupper($cabinClass),
                    $requiredMiles
                ));
            }

            // Penebusan poin miles dari akun anggota FFP
            $this->loyaltyService->redeemMiles(
                $account->id,
                $requiredMiles,
                sprintf('Penebusan tiket award %s penerbangan %s', strtoupper($cabinClass), $flight->flight_number)
            );

            // Hitung pajak bandara tunai yang tetap wajib dibayarkan oleh penumpang
            $originIata = $flight->departureAirport->iata_code ?? 'CGK';
            $taxCalc = $this->taxService->decomposeTaxesAndFees(
                (int) $flight->base_price_minor_unit,
                $originIata
            );
            $taxesIdr = (int) round(($taxCalc['pjp2u_airport_tax_minor_unit'] + $taxCalc['iwjr_insurance_minor_unit']) / 100);

            $tktNum = sprintf('TKT-AWD-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));

            $ticket = AviationTicket::create([
                'flight_id' => $flight->id,
                'ticket_number' => $tktNum,
                'passenger_name' => $passengerName,
                'seat_number' => $isBusiness ? '2A' : '15C',
                'booking_reference' => strtoupper(substr(uniqid('AWD', true), 0, 6)),
                'status' => 'confirmed',
                'total_price_minor_unit' => $taxCalc['pjp2u_airport_tax_minor_unit'] + $taxCalc['iwjr_insurance_minor_unit'], // Hanya pajak
            ]);

            return [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'miles_redeemed' => $requiredMiles,
                'taxes_and_fees_idr' => $taxesIdr,
                'booking_status' => 'CONFIRMED_AWARD_ISSUED',
            ];
        });
    }
}
