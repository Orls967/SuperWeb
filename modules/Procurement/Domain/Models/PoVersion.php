<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoVersion extends Model
{
    protected $table = 'prc_po_versions';

    protected $fillable = ['po_id', 'version', 'change_summary', 'snapshot', 'status', 'approval_id', 'created_by_user_id'];

    protected $casts = ['version' => 'integer', 'snapshot' => 'array', 'approval_id' => 'integer', 'created_by_user_id' => 'integer'];

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}
