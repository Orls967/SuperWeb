<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

/**
 * Algoritma A9 Shared: PM 89/2015 Passenger Care Obligation Calculator.
 */
class PassengerCareObligationCalculator
{
    /**
     * Evaluasi hak kepedulian penumpang dan terbitkan sertifikat regulasi.
     */
    public function evaluate(int $delayMinutes, string $incidentCategory): PassengerCareCertificateResult
    {
        $normalizedIncident = strtoupper($incidentCategory);
        $isForceMajeure = str_starts_with($normalizedIncident, 'FORCE_MAJEURE') ||
            $normalizedIncident === 'ATC_RESTRICTION' ||
            $normalizedIncident === 'AIRPORT_INFRASTRUCTURE';

        $isCancellation = $normalizedIncident === 'CANCELLATION' || $delayMinutes >= 720;

        if ($isCancellation) {
            $cat = 'CAT6_CANCELLED';
            $care = true;
            $vType = 'meal';
            $vNominal = 15000000;
            $cashRequired = ! $isForceMajeure;
            $cashAmount = ! $isForceMajeure ? 30000000 : 0;
            $hotel = true;
        } elseif ($delayMinutes > 240) {
            $cat = 'CAT5';
            $care = true;
            $vType = 'meal';
            $vNominal = 12500000;
            $cashRequired = ! $isForceMajeure;
            $cashAmount = ! $isForceMajeure ? 30000000 : 0;
            $hotel = $delayMinutes >= 480;
        } elseif ($delayMinutes >= 181) {
            $cat = 'CAT4';
            $care = true;
            $vType = 'meal';
            $vNominal = 10000000;
            $cashRequired = false;
            $cashAmount = 0;
            $hotel = false;
        } elseif ($delayMinutes >= 121) {
            $cat = 'CAT3';
            $care = true;
            $vType = 'meal';
            $vNominal = 7500000;
            $cashRequired = false;
            $cashAmount = 0;
            $hotel = false;
        } elseif ($delayMinutes >= 61) {
            $cat = 'CAT2';
            $care = true;
            $vType = 'snack';
            $vNominal = 5000000;
            $cashRequired = false;
            $cashAmount = 0;
            $hotel = false;
        } elseif ($delayMinutes >= 30) {
            $cat = 'CAT1';
            $care = true;
            $vType = 'refreshment';
            $vNominal = 2500000;
            $cashRequired = false;
            $cashAmount = 0;
            $hotel = false;
        } else {
            $cat = 'BELOW_THRESHOLD';
            $care = false;
            $vType = null;
            $vNominal = 0;
            $cashRequired = false;
            $cashAmount = 0;
            $hotel = false;
        }

        $cert = PassengerCareCertificate::create($cat, $delayMinutes, $care, $cashAmount);

        return new PassengerCareCertificateResult(
            $cat,
            $delayMinutes,
            $care,
            $vType,
            $vNominal,
            $cashRequired,
            $cashAmount,
            $hotel,
            $cert
        );
    }
}

