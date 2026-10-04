<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClauseTemplate extends Model
{
    use HasUuids;

    protected $table = 'ctr_clause_templates';

    protected $fillable = [
        'code', 'title', 'category', 'body_template',
        'version', 'is_standard', 'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_standard' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Render the clause template replacing {{variable}} placeholders with given values.
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(array $variables): string
    {
        $rendered = $this->body_template;
        foreach ($variables as $key => $val) {
            $rendered = str_replace("{{{$key}}}", (string) $val, $rendered);
        }

        return $rendered;
    }
}
