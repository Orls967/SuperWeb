<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

class DgSegregationValidator
{
    /**
     * Segregation matrix based on IMDG Code for co-loading Dangerous Goods in a single freight container.
     * True means compatible, False means prohibited from co-loading.
     *
     * @var array<string, array<string, bool>>
     */
    private static array $incompatibilityMatrix = [
        '1' => [
            '2.1' => false,
            '3' => false,
            '4.1' => false,
            '4.2' => false,
            '4.3' => false,
            '5.1' => false,
            '5.2' => false,
            '8' => false,
        ],
        '3' => [
            '1' => false,
            '5.1' => false,
        ],
        '5.1' => [
            '1' => false,
            '3' => false,
        ],
        '8' => [
            '1' => false,
        ],
    ];

    /**
     * Check if two DG classes can be loaded together in the same container/unit.
     */
    public static function isCompatible(?string $classA, ?string $classB): bool
    {
        if (empty($classA) || empty($classB)) {
            return true; // Non-DG or unspecified is compatible
        }

        $baseA = explode('.', trim($classA))[0];
        $baseB = explode('.', trim($classB))[0];

        // Check A against B
        if (isset(self::$incompatibilityMatrix[$baseA][$baseB]) && ! self::$incompatibilityMatrix[$baseA][$baseB]) {
            return false;
        }

        // Check B against A (symmetric)
        if (isset(self::$incompatibilityMatrix[$baseB][$baseA]) && ! self::$incompatibilityMatrix[$baseB][$baseA]) {
            return false;
        }

        return true;
    }
}
