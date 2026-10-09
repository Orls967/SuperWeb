<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDocument extends Model
{
    protected $table = 'sup_documents';

    protected $fillable = ['supplier_id', 'document_id', 'kind', 'label'];

    protected $casts = ['document_id' => 'integer'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
