<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Routing;

use InvalidArgumentException;

final class HousekeepingRouteCertificateVerifier
{
    public static function verify(HousekeepingRouteCertificate $certificate): bool
    {
        foreach ($certificate->workloads as $staff => $load) {
            if ($load > $certificate->creditLimit) {
                throw new InvalidArgumentException("Sertifikat rute housekeeping tidak valid: beban kerja {$staff} ({$load}) melampaui limit kredit.");
            }
        }
        foreach ($certificate->assignments as $staff => $assignedList) {
            if (count($assignedList) !== count($certificate->routes[$staff])) {
                throw new InvalidArgumentException("Sertifikat rute housekeeping tidak valid: jumlah rute tidak sama dengan kamar yang dialokasikan.");
            }
        }
        return true;
    }
}
