<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Identity;

use InvalidArgumentException;

/**
 * Multi-Profile Identity Linkage Weighting Matrix (W8-D56).
 *
 * Configures distinct attribute matching weights and threshold bands across different
 * entity archetypes (Individual Citizens, Medical Patients, Corporate Suppliers, Logistics Fleet Carriers).
 */
final class IdentityScoreMatrix
{
    public const PROFILE_CITIZEN = 'CITIZEN';

    public const PROFILE_PATIENT = 'PATIENT';

    public const PROFILE_SUPPLIER = 'SUPPLIER';

    public const PROFILE_CARRIER = 'CARRIER';

    /**
     * @var array<string, array<string, float>>
     */
    private static array $matrixProfiles = [
        self::PROFILE_CITIZEN => [
            'nik' => 0.40,
            'phone' => 0.20,
            'email' => 0.15,
            'name' => 0.15,
            'dob' => 0.10,
        ],
        self::PROFILE_PATIENT => [
            'nik' => 0.35,
            'bpjs_number' => 0.30,
            'name' => 0.15,
            'dob' => 0.15,
            'phone' => 0.05,
        ],
        self::PROFILE_SUPPLIER => [
            'tax_id' => 0.45,
            'legal_entity_name' => 0.25,
            'business_license' => 0.20,
            'bank_account' => 0.10,
        ],
        self::PROFILE_CARRIER => [
            'carrier_permit_code' => 0.40,
            'fleet_dot_number' => 0.30,
            'company_name' => 0.20,
            'dispatch_contact' => 0.10,
        ],
    ];

    /**
     * Retrieve weights for a given entity archetype.
     *
     * @return array<string, float>
     */
    public static function getWeights(string $archetype): array
    {
        $key = strtoupper($archetype);
        if (! isset(self::$matrixProfiles[$key])) {
            return self::$matrixProfiles[self::PROFILE_CITIZEN];
        }

        return self::$matrixProfiles[$key];
    }

    /**
     * Validate that an attribute weights map normalizes to 1.0 (with small float tolerance).
     *
     * @param  array<string, float>  $weights
     */
    public static function validateNormalization(array $weights): bool
    {
        $sum = array_sum($weights);

        return abs($sum - 1.0) < 0.01;
    }

    /**
     * Register or override archetype weights map.
     *
     * @param  array<string, float>  $weights
     */
    public static function registerProfile(string $archetype, array $weights): void
    {
        if (! self::validateNormalization($weights)) {
            throw new InvalidArgumentException(
                sprintf('Weights for archetype [%s] must sum to 1.0, got: %f', $archetype, array_sum($weights))
            );
        }

        self::$matrixProfiles[strtoupper($archetype)] = $weights;
    }
}
