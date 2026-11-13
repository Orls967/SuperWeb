<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

/**
 * Sertifikat verifikasi deterministik untuk J1 AutomationRoiPlanner (tanpa float).
 */
final readonly class AutomationPlanCertificate
{
    public function __construct(
        public string $warehouseId,
        public int $capexIdr,
        public int $annualSavingsIdr,
        public int $roiBps,
        public int $paybackMonths,
        public string $hash,
    ) {}

    public function isValid(): bool
    {
        if ($this->capexIdr <= 0 || $this->annualSavingsIdr < 0) {
            return false;
        }

        $expectedRoiBps = (int) intdiv($this->annualSavingsIdr * 10000, $this->capexIdr);
        if ($this->roiBps !== $expectedRoiBps) {
            return false;
        }

        return $this->hash === self::computeHash(
            $this->warehouseId,
            $this->capexIdr,
            $this->annualSavingsIdr,
            $this->roiBps,
            $this->paybackMonths,
        );
    }

    public static function computeHash(
        string $warehouseId,
        int $capexIdr,
        int $annualSavingsIdr,
        int $roiBps,
        int $paybackMonths,
    ): string {
        return hash('sha256', implode('|', [
            $warehouseId,
            (string) $capexIdr,
            (string) $annualSavingsIdr,
            (string) $roiBps,
            (string) $paybackMonths,
        ]));
    }
}
