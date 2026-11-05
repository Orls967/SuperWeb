<?php

declare(strict_types=1);

namespace Modules\Rwa\Contracts;

use Carbon\CarbonImmutable;

interface SukukTokenRegistry
{
    /**
     * Menerbitkan instrumen sukuk terikat aset riil ke dalam pencatatan RWA.
     *
     * @param  string  $assetCode  Kode unik aset RWA (misal 'SKK-HQ-BLDG')
     * @param  int  $faceValueIdr  Total nilai nominal sukuk (IDR integer)
     * @param  int  $units  Jumlah token/unit sukuk
     * @param  CarbonImmutable  $maturity  Tanggal jatuh tempo pelunasan
     * @param  string  $idempotencyKey  Kunci idempotensi penerbitan
     * @return int ID aset RWA yang terbit
     */
    public function issue(string $assetCode, int $faceValueIdr, int $units, CarbonImmutable $maturity, string $idempotencyKey): int;

    /**
     * Melunasi / menebus (redeem) sukuk saat jatuh tempo dan membakar token.
     *
     * @param  string  $assetCode  Kode aset RWA
     * @param  CarbonImmutable  $at  Waktu penebusan
     * @param  string  $idempotencyKey  Kunci idempotensi penebusan
     */
    public function redeem(string $assetCode, CarbonImmutable $at, string $idempotencyKey): void;
}
