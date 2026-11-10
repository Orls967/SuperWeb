<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchTerm;

/**
 * Service pemadatan dan optimasi inverted index (Index Compaction & Garbage Collection).
 */
class InvertedIndexCompactor
{
    /**
     * Menjalankan pembersihan dan optimasi tabel posting term.
     *
     * @return array{dangling_deleted: int, orphan_indices_cleaned: int}
     */
    public function compact(): array
    {
        // 1. Hapus term yang tidak memiliki dokumen induk
        $danglingDeleted = CoreSearchTerm::query()
            ->whereNotIn('search_index_id', CoreSearchIndex::query()->select('id'))
            ->delete();

        // 2. Normalisasi panjang dokumen jika 0
        $emptyLengthDocs = CoreSearchIndex::query()->where('document_length', '<=', 0)->get();
        $orphanCleaned = 0;

        foreach ($emptyLengthDocs as $doc) {
            $termCount = CoreSearchTerm::query()->where('search_index_id', $doc->id)->count();
            if ($termCount > 0) {
                $doc->document_length = $termCount;
                $doc->save();
                $orphanCleaned++;
            }
        }

        return [
            'dangling_deleted' => $danglingDeleted,
            'orphan_indices_cleaned' => $orphanCleaned,
        ];
    }
}
