<?php

declare(strict_types=1);

use Modules\Port\Domain\Services\MaritimeVesselGeometryCalculator;

describe('Maritime Vessel Geometry & Intact Stability Suite', function (): void {
    it('computes hull form coefficients (Cb, Cm, Cp, Cwp) accurately', function (): void {
        $calc = new MaritimeVesselGeometryCalculator;

        // Supramax Bulk Carrier: LBP 185m, B 32.26m, Draft 12.5m, Volume 60,000 m3
        $coeffs = $calc->calculateHullFormCoefficients(
            60000.0,
            185.0,
            32.26,
            12.50,
            395.0,
            5100.0
        );

        expect($coeffs['block_coefficient_cb'])->toBeGreaterThan(0.75) // Bulk carrier Cb ~0.80
            ->and($coeffs['block_coefficient_cb'])->toBeLessThan(0.85)
            ->and($coeffs['midship_coefficient_cm'])->toBeGreaterThan(0.95);
    });

    it('evaluates intact stability GM and checks IMO minimum requirement of 0.15m', function (): void {
        $calc = new MaritimeVesselGeometryCalculator;

        // Kondisi stabil: KB 6.2m, BM 5.8m (KM = 12.0m), KG 10.5m -> Solid GM 1.5m, Fluid GM 1.45m
        $res = $calc->evaluateIntactStabilityGm(6.20, 5.80, 10.50, 0.05);

        expect($res['is_imo_compliant'])->toBeTrue()
            ->and($res['fluid_gm_m'])->toBe(1.45)
            ->and($res['status'])->toBe('STABLE_IMO_COMPLIANT');

        // Kondisi tender berbahaya: KG terlalu tinggi (11.95m) -> Fluid GM 0.00m
        $danger = $calc->evaluateIntactStabilityGm(6.20, 5.80, 11.95, 0.05);
        expect($danger['is_imo_compliant'])->toBeFalse()
            ->and($danger['status'])->toBe('CRITICAL_TENDER_NEGATIVE_STABILITY');
    });
});
