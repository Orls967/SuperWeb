<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use InvalidArgumentException;

class AssetMismatchException extends InvalidArgumentException
{
    public function __construct(string $entryAssetCode, string $accountCode, string $accountAssetCode)
    {
        parent::__construct(
            "Asset code entri '{$entryAssetCode}' tidak cocok dengan asset code akun '{$accountCode}' ('{$accountAssetCode}')."
        );
    }
}
