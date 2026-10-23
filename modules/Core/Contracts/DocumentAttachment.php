<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for DocumentAttachment model.
 */
class DocumentAttachment extends Model
{
    protected $table = 'core_documents';
}
