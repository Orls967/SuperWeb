<?php

declare(strict_types=1);

namespace Modules\Core\Application\Listeners;

use Illuminate\Support\Str;
use Modules\Core\Application\Actions\Search\TokenExtractor;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchTerm;

/**
 * Event subscriber untuk sinkronisasi indeks pencarian real-time saat terjadi mutasi entitas domain.
 */
class SearchIndexEventSubscriber
{
    public function __construct(
        protected TokenExtractor $tokenExtractor,
    ) {}

    /**
     * Memperbarui atau membuat entri indeks pencarian secara otomatis dari data domain.
     *
     * @param  array{
     *     module: string,
     *     entity_type: string,
     *     entity_id: string,
     *     title: string,
     *     content: string,
     *     metadata?: array<string, mixed>,
     *     required_permission?: string|null,
     *     scope_type?: string|null,
     *     scope_id?: string|null
     * }  $payload
     */
    public function syncEntity(array $payload): CoreSearchIndex
    {
        $index = CoreSearchIndex::updateOrCreate(
            [
                'module' => $payload['module'],
                'entity_type' => $payload['entity_type'],
                'entity_id' => (string) $payload['entity_id'],
            ],
            [
                'id' => (string) ($payload['id'] ?? Str::uuid()),
                'title' => $payload['title'],
                'content' => $payload['content'],
                'metadata' => $payload['metadata'] ?? [],
                'required_permission' => $payload['required_permission'] ?? null,
                'scope_type' => $payload['scope_type'] ?? null,
                'scope_id' => $payload['scope_id'] ?? null,
                'indexed_at' => now(),
            ]
        );

        // Perbarui posting list term
        CoreSearchTerm::where('search_index_id', $index->id)->delete();

        $tokens = $this->tokenExtractor->extractTokens($index->title.' '.$index->content);
        foreach ($tokens as $token => $freq) {
            CoreSearchTerm::create([
                'search_index_id' => $index->id,
                'term' => $token,
                'term_hash' => hash('crc32b', $token),
                'frequency' => $freq,
                'field_weight' => stripos($index->title, $token) !== false ? 3 : 1,
            ]);
        }

        $index->document_length = count($tokens);
        $index->save();

        return $index;
    }
}
