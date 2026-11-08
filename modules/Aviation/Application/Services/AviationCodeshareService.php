<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Modules\Aviation\Application\Sagas\CodeshareSettlementSaga;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;
use Modules\Aviation\Domain\Models\AviationCodeshareFlight;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\StateMachines\CodeshareAgreementStateMachine;

class AviationCodeshareService
{
    public function __construct(
        protected CodeshareAgreementStateMachine $stateMachine,
        protected CodeshareSettlementSaga $settlementSaga
    ) {}

    /**
     * Buat usulan perjanjian codeshare baru antar-maskapai.
     *
     * @param array{
     *     operating_airline_id: int,
     *     marketing_airline_id: int,
     *     agreement_code: string,
     *     agreement_type: string,
     *     revenue_split_bps: int,
     *     valid_from: string,
     *     valid_until: string
     * } $data
     */
    public function createAgreement(array $data): AviationCodeshareAgreement
    {
        return AviationCodeshareAgreement::create([
            'operating_airline_id' => $data['operating_airline_id'],
            'marketing_airline_id' => $data['marketing_airline_id'],
            'agreement_code' => $data['agreement_code'],
            'agreement_type' => $data['agreement_type'],
            'revenue_split_bps' => $data['revenue_split_bps'] ?? 8500,
            'proration_rule' => $data['proration_rule'] ?? 'straight_rate',
            'status' => CodeshareAgreementStateMachine::STATUS_PROPOSED,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
        ]);
    }

    /**
     * Aktifkan perjanjian codeshare yang telah ditandatangani.
     */
    public function activateAgreement(int $agreementId): AviationCodeshareAgreement
    {
        $agreement = AviationCodeshareAgreement::findOrFail($agreementId);

        if ($agreement->status === CodeshareAgreementStateMachine::STATUS_PROPOSED) {
            $agreement->status = $this->stateMachine->transition($agreement->status, CodeshareAgreementStateMachine::STATUS_NEGOTIATING);
        }

        if ($agreement->status === CodeshareAgreementStateMachine::STATUS_NEGOTIATING) {
            $agreement->status = $this->stateMachine->transition($agreement->status, CodeshareAgreementStateMachine::STATUS_SIGNED);
        }

        if ($agreement->status === CodeshareAgreementStateMachine::STATUS_SIGNED) {
            $agreement->status = $this->stateMachine->transition($agreement->status, CodeshareAgreementStateMachine::STATUS_ACTIVE);
        }

        $agreement->save();

        return $agreement;
    }

    /**
     * Petakan nomor penerbangan marketing partner ke penerbangan operating fisik.
     */
    public function mapCodeshareFlight(int $agreementId, int $flightId, string $marketingFlightNumber, int $seats = 0): AviationCodeshareFlight
    {
        $agreement = AviationCodeshareAgreement::findOrFail($agreementId);
        $flight = AviationFlight::findOrFail($flightId);

        return AviationCodeshareFlight::updateOrCreate(
            [
                'codeshare_agreement_id' => $agreement->id,
                'operating_flight_id' => $flight->id,
                'marketing_flight_number' => $marketingFlightNumber,
            ],
            [
                'allotted_seats' => $seats,
                'status' => 'active',
            ]
        );
    }

    /**
     * Selesaikan kliring bagi hasil codeshare untuk tiket tertentu via Saga.
     */
    public function settleTicket(int $agreementId, int $ticketId, int $tpmDistance): array
    {
        return $this->settlementSaga->execute($agreementId, $ticketId, $tpmDistance);
    }
}
