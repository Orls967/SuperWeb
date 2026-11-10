<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchTerm;
use Modules\Shared\Domain\Algorithms\Ui\TrigramJaccardMatcher;

/**
 * Service diagnosis kueri tanpa hasil (Zero-Result Query Investigation).
 * Menganalisis penyebab kegagalan temu balik dokumen dan menyarankan langkah perbaikan konten.
 */
class ZeroResultInvestigator
{
    public function __construct(
        protected TokenExtractor $tokenExtractor,
        protected TrigramJaccardMatcher $jaccardMatcher,
    ) {}

    /**
     * Mendiagnosis kueri pencarian tertentu yang mengembalikan 0 hasil.
     *
     * @return array{
     *     query: string,
     *     diagnosis: string,
     *     possible_typo: string|null,
     *     rbac_restricted_count: int,
     *     recommended_action: string
     * }
     */
    public function investigate(string $query): array
    {
        $tokens = $this->tokenExtractor->extractTokens($query);
        if (empty($tokens)) {
            return [
                'query' => $query,
                'diagnosis' => 'Kueri terlalu pendek atau hanya terdiri dari kata henti (stopwords).',
                'possible_typo' => null,
                'rbac_restricted_count' => 0,
                'recommended_action' => 'Masukkan kata kunci yang lebih spesifik atau nomor referensi dokumen.',
            ];
        }

        // Cek potensi saltik terhadap kamus term
        $corpus = CoreSearchTerm::query()->select('term')->distinct()->limit(500)->pluck('term')->toArray();
        $possibleTypo = null;
        foreach (array_keys($tokens) as $t) {
            $match = $this->jaccardMatcher->findBestMatch($t, $corpus, 0.40);
            if ($match !== null && $match['match'] !== $t) {
                $possibleTypo = $match['match'];
                break;
            }
        }

        if ($possibleTypo !== null) {
            return [
                'query' => $query,
                'diagnosis' => sprintf('Terdeteksi kemungkinan saltik (typo). Kata [%s] mirip dengan term terindeks [%s].', key($tokens), $possibleTypo),
                'possible_typo' => $possibleTypo,
                'rbac_restricted_count' => 0,
                'recommended_action' => 'Gunakan fitur auto-suggest atau perbaiki ejaan kueri.',
            ];
        }

        // Cek apakah dokumen sebenarnya ada tetapi tersembunyi oleh izin rahasia / RBAC
        $restrictedCount = CoreSearchIndex::query()
            ->whereNotNull('required_permission')
            ->where(function ($q) use ($tokens) {
                foreach (array_keys($tokens) as $t) {
                    $q->orWhere('title', 'LIKE', "%{$t}%")->orWhere('content', 'LIKE', "%{$t}%");
                }
            })
            ->count();

        if ($restrictedCount > 0) {
            return [
                'query' => $query,
                'diagnosis' => sprintf('Ditemukan %d dokumen yang cocok tetapi dilindungi oleh izin hak akses (RBAC).', $restrictedCount),
                'possible_typo' => null,
                'rbac_restricted_count' => $restrictedCount,
                'recommended_action' => 'Hasil disaring demi kepatuhan zero-leak. Ajukan izin akses jika dokumen diperlukan.',
            ];
        }

        return [
            'query' => $query,
            'diagnosis' => 'Tidak ditemukan dokumen relevan dalam pangkalan data terindeks.',
            'possible_typo' => null,
            'rbac_restricted_count' => 0,
            'recommended_action' => 'Pastikan dokumen telah dibuat di modul terkait atau jalankan reindex.',
        ];
    }
}
