<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractClause extends Model
{
    protected $table = 'ctr_contract_clauses';

    protected $fillable = [
        'contract_id', 'clause_template_id', 'display_order',
        'title', 'body', 'is_negotiated',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_negotiated' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function clauseTemplate(): BelongsTo
    {
        return $this->belongsTo(ClauseTemplate::class, 'clause_template_id');
    }
}
