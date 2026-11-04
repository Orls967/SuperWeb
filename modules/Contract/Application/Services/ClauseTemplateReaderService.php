<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Modules\Contract\Contracts\ClauseTemplateReader;
use Modules\Contract\Domain\Models\ClauseTemplate;

class ClauseTemplateReaderService implements ClauseTemplateReader
{
    /**
     * @return list<array{code: string, title: string, body: string, dispute_count: int}>
     */
    public function templatesForCategory(string $category): array
    {
        return ClauseTemplate::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get()
            ->map(fn (ClauseTemplate $t): array => [
                'code' => (string) $t->code,
                'title' => (string) $t->title,
                'body' => (string) $t->body_template,
                'dispute_count' => (int) ($t->dispute_count ?? 0),
            ])
            ->values()
            ->all();
    }
}
