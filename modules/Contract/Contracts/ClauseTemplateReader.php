<?php

declare(strict_types=1);

namespace Modules\Contract\Contracts;

interface ClauseTemplateReader
{
    /**
     * Mengembalikan daftar template klausul terurut berdasarkan code menaik (K13).
     *
     * @return list<array{code: string, title: string, body: string, dispute_count: int}>
     */
    public function templatesForCategory(string $category): array;
}
