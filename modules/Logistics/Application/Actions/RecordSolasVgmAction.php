<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Brick\Math\BigDecimal;
use InvalidArgumentException;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Load;

class RecordSolasVgmAction
{
    /**
     * Record Verified Gross Mass (VGM) for a freight container under SOLAS Chapter VI.
     */
    public function execute(
        Load $load,
        float|string|BigDecimal $vgmKg,
        string $method = 'method_1',
        string $certifiedBy = 'Petugas Timbang Pelabuhan'
    ): Load {
        if ($load->load_type !== 'container') {
            throw new InvalidArgumentException('Hanya muatan bertipe kontainer yang memerlukan sertifikasi SOLAS VGM.');
        }

        $vgmAmount = $vgmKg instanceof BigDecimal ? $vgmKg : BigDecimal::of((string) $vgmKg);

        if ($load->loadable instanceof Container) {
            $tareKg = BigDecimal::of((string) $load->loadable->tare_kg);
            if ($vgmAmount->isLessThan($tareKg)) {
                throw new InvalidArgumentException("Berat VGM ({$vgmAmount} kg) tidak boleh lebih kecil dari berat tara kosong kontainer ({$tareKg} kg).");
            }
        }

        $load->recordVgm($vgmAmount, $method, $certifiedBy);

        return $load;
    }
}
