<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use DomainException;

/**
 * Verifikator independen untuk CycleCountScheduleCertificate (H3).
 */
final class CycleCountScheduleCertificateVerifier
{
    public function verify(CycleCountScheduleCertificate $certificate): bool
    {
        if (count($certificate->selected) > $certificate->capacity) {
            throw new DomainException('Jumlah item terpilih melebihi kapasitas cycle count.');
        }

        foreach ($certificate->selected as $item) {
            if ($item['overdue'] < 0) {
                throw new DomainException("Item {$item['id']} belum jatuh tempo (overdue < 0).");
            }
        }

        if ($certificate->selected !== [] && $certificate->carriedOver !== []) {
            $lastSelected = $certificate->selected[count($certificate->selected) - 1];
            $firstCarried = $certificate->carriedOver[0];
            if ($firstCarried['overdue'] > $lastSelected['overdue']) {
                throw new DomainException('Urutan prioritas overdue tidak konsisten antara selected dan carried_over.');
            }
        }

        return true;
    }
}
