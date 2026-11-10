<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Core\Domain\Models\CoreSearchTerm;

final class SearchAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'search:audit';
    }

    public function corrupt(): string
    {
        // Suntikkan posting term yatim piatu (dangling) tanpa dokumen indeks induk
        CoreSearchTerm::create([
            'search_index_id' => 'non-existent-parent-idx-99999',
            'term' => 'corruptedterm',
            'term_hash' => hash('crc32b', 'corruptedterm'),
            'frequency' => 99,
            'field_weight' => 1,
        ]);

        return 'dangling posting term tanpa induk dokumen indeks';
    }
}
