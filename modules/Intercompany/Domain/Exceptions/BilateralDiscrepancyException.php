<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Exceptions;

use RuntimeException;

class BilateralDiscrepancyException extends RuntimeException
{
    public function __construct(string $seller, string $buyer, int $receivable, int $payable, int $delta)
    {
        parent::__construct(
            "Diskrepansi rekonsiliasi bilateral terdeteksi antara [{$seller}] dan [{$buyer}]: AR = {$receivable}, AP = {$payable}, Selisih = {$delta} IDR melebihi batas toleransi."
        );
    }
}
