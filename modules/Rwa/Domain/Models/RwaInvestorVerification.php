<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaInvestorVerification extends Model
{
    protected $table = 'rwa_investor_verifications';

    protected $fillable = [
        'user_id',
        'kyc_document_id',
        'approval_id',
        'status',
        'berlaku_sampai',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'berlaku_sampai' => 'date',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
