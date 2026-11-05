<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybWriteoffApproval extends Model
{
    protected $table = 'syb_writeoff_approvals';

    protected $fillable = [
        'financing_ref',
        'customer_ref',
        'written_off_principal_minor',
        'written_off_margin_minor',
        'total_writeoff_minor',
        'dps_approval_ref',
        'board_approval_ref',
        'status',
        'writeoff_reason',
        'certificate_hash',
        'approved_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'written_off_principal_minor' => 'integer',
            'written_off_margin_minor' => 'integer',
            'total_writeoff_minor' => 'integer',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }
}
