<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\ValueObjects;

/**
 * Value Object: Aviation Fare Ladder & RBD Rule Structure.
 *
 * Mengelola struktur aturan kelas reservasi (Reservation Booking Designator / RBD),
 * batasan pembelian di muka (Advance Purchase / AP), batas minimal tinggal (Minimum Stay),
 * fleksibilitas perubahan jadwal, serta denda pembatalan tiket.
 */
final class AviationFareLadder
{
    /**
     * @var array<string, array{
     *     cabin: string,
     *     multiplier: float,
     *     advance_purchase_days: int,
     *     is_refundable: bool,
     *     refund_fee_percentage: float,
     *     reschedule_allowed: bool,
     *     reschedule_fee_idr: int,
     *     mileage_accrual_rate: float
     * }>
     */
    private const FARE_RULES = [
        'F' => [
            'cabin' => 'first',
            'multiplier' => 2.50,
            'advance_purchase_days' => 0,
            'is_refundable' => true,
            'refund_fee_percentage' => 0.0,
            'reschedule_allowed' => true,
            'reschedule_fee_idr' => 0,
            'mileage_accrual_rate' => 2.00,
        ],
        'J' => [
            'cabin' => 'business',
            'multiplier' => 1.80,
            'advance_purchase_days' => 0,
            'is_refundable' => true,
            'refund_fee_percentage' => 5.0,
            'reschedule_allowed' => true,
            'reschedule_fee_idr' => 0,
            'mileage_accrual_rate' => 1.50,
        ],
        'C' => [
            'cabin' => 'business',
            'multiplier' => 1.50,
            'advance_purchase_days' => 3,
            'is_refundable' => true,
            'refund_fee_percentage' => 10.0,
            'reschedule_allowed' => true,
            'reschedule_fee_idr' => 100000,
            'mileage_accrual_rate' => 1.25,
        ],
        'Y' => [
            'cabin' => 'economy',
            'multiplier' => 1.00,
            'advance_purchase_days' => 0,
            'is_refundable' => true,
            'refund_fee_percentage' => 10.0,
            'reschedule_allowed' => true,
            'reschedule_fee_idr' => 50000,
            'mileage_accrual_rate' => 1.00,
        ],
        'B' => [
            'cabin' => 'economy',
            'multiplier' => 0.85,
            'advance_purchase_days' => 3,
            'is_refundable' => true,
            'refund_fee_percentage' => 20.0,
            'reschedule_allowed' => true,
            'reschedule_fee_idr' => 150000,
            'mileage_accrual_rate' => 0.90,
        ],
        'M' => [
            'cabin' => 'economy',
            'multiplier' => 0.70,
            'advance_purchase_days' => 7,
            'is_refundable' => true,
            'refund_fee_percentage' => 30.0,
            'reschedule_allowed' => true,
            'reschedule_fee_idr' => 200000,
            'mileage_accrual_rate' => 0.75,
        ],
        'Q' => [
            'cabin' => 'economy',
            'multiplier' => 0.55,
            'advance_purchase_days' => 14,
            'is_refundable' => false,
            'refund_fee_percentage' => 100.0,
            'reschedule_allowed' => true,
            'reschedule_fee_idr' => 300000,
            'mileage_accrual_rate' => 0.50,
        ],
        'V' => [
            'cabin' => 'economy',
            'multiplier' => 0.40,
            'advance_purchase_days' => 21,
            'is_refundable' => false,
            'refund_fee_percentage' => 100.0,
            'reschedule_allowed' => false,
            'reschedule_fee_idr' => 0,
            'mileage_accrual_rate' => 0.25,
        ],
    ];

    /**
     * Dapatkan spesifikasi aturan tarif untuk kode bucket tertentu.
     */
    public static function getRules(string $bucketCode): array
    {
        $code = strtoupper($bucketCode);

        return self::FARE_RULES[$code] ?? self::FARE_RULES['Y'];
    }

    /**
     * Hitung nilai pengembalian dana tiket (refund amount) berdasarkan aturan kelas tarif.
     *
     * @return array{
     *     is_refundable: bool,
     *     cancellation_fee_minor_unit: int,
     *     net_refund_minor_unit: int
     * }
     */
    public static function calculateRefund(string $bucketCode, int $ticketFareMinorUnit): array
    {
        $rules = self::getRules($bucketCode);

        if (! $rules['is_refundable']) {
            return [
                'is_refundable' => false,
                'cancellation_fee_minor_unit' => $ticketFareMinorUnit,
                'net_refund_minor_unit' => 0,
            ];
        }

        $fee = (int) round(($ticketFareMinorUnit * $rules['refund_fee_percentage']) / 100.0);
        $netRefund = max(0, $ticketFareMinorUnit - $fee);

        return [
            'is_refundable' => true,
            'cancellation_fee_minor_unit' => $fee,
            'net_refund_minor_unit' => $netRefund,
        ];
    }
}
