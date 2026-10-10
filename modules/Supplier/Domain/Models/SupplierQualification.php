<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQualification extends Model
{
    protected $table = 'sup_qualifications';

    protected $fillable = [
        'supplier_id', 'type', 'answers', 'scores', 'total_score', 'result',
        'approval_id', 'approval_status', 'assessed_by_user_id', 'notes',
    ];

    protected $casts = ['answers' => 'array', 'scores' => 'array', 'total_score' => 'integer', 'approval_id' => 'string'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }
}
