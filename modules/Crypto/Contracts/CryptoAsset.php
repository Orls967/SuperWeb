<?php

declare(strict_types=1);

namespace Modules\Crypto\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for CryptoAsset model.
 */
class CryptoAsset extends Model
{
    protected $table = 'crypto_assets';
}
