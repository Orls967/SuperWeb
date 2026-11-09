<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\ValueObjects;

final class PrescriptionSafetyScore
{
    public const LEVEL_SAFE = 'SAFE';

    public const LEVEL_CAUTION = 'CAUTION';

    public const LEVEL_CRITICAL = 'CRITICAL';

    public const LEVEL_BLOCKED = 'BLOCKED';

    /**
     * @param  array<int, string>  $contraindications
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly int $scorePoints,
        public readonly string $safetyLevel,
        public readonly bool $isDispensable,
        public readonly bool $requiresDualDoctorApproval,
        public readonly array $contraindications = [],
        public readonly array $warnings = []
    ) {}

    public static function safe(): self
    {
        return new self(
            scorePoints: 100,
            safetyLevel: self::LEVEL_SAFE,
            isDispensable: true,
            requiresDualDoctorApproval: false,
            contraindications: [],
            warnings: []
        );
    }

    public static function blocked(string $reason): self
    {
        return new self(
            scorePoints: 0,
            safetyLevel: self::LEVEL_BLOCKED,
            isDispensable: false,
            requiresDualDoctorApproval: true,
            contraindications: [$reason],
            warnings: []
        );
    }

    public static function cautionWithDualApproval(int $points, array $contraindications, array $warnings): self
    {
        return new self(
            scorePoints: max(1, min(99, $points)),
            safetyLevel: self::LEVEL_CAUTION,
            isDispensable: true,
            requiresDualDoctorApproval: true,
            contraindications: $contraindications,
            warnings: $warnings
        );
    }

    public function toArray(): array
    {
        return [
            'score_points' => $this->scorePoints,
            'safety_level' => $this->safetyLevel,
            'is_dispensable' => $this->isDispensable,
            'requires_dual_doctor_approval' => $this->requiresDualDoctorApproval,
            'contraindications' => $this->contraindications,
            'warnings' => $this->warnings,
        ];
    }
}
