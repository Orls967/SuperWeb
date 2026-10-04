<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierStatusHistory extends Model
{
    protected $table = 'sup_status_histories';

    protected $fillable = ['supplier_id', 'from_status', 'to_status', 'reason', 'changed_by_user_id'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
