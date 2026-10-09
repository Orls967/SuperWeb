<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RwaHolding extends Model
{
    protected $table = 'rwa_holdings';

    protected $fillable = [
        'rwa_asset_id',
        'user_id',
        'token_balance',
    ];

    protected $casts = [
        'token_balance' => 'integer',
    ];

    public function asset()
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
