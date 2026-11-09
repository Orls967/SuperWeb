<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Hospital\Domain\Models\LaboratoryLoincCatalog;

/**
 * Layanan Pencarian & Pencocokan Katalog LOINC 10.000 Parameter (Fase 105).
 */
class LoincCatalogSearchService
{
    /**
     * Mencari parameter laboratorium berdasarkan kata kunci leksikal atau kode LOINC.
     *
     * @return Collection<int, LaboratoryLoincCatalog>
     */
    public function search(
        string $query,
        ?string $className = null,
        ?string $systemSpecimen = null,
        bool $onlyOrderable = true,
        int $limit = 50
    ): Collection {
        $builder = LaboratoryLoincCatalog::query();

        if ($onlyOrderable) {
            $builder->where('is_orderable', true);
        }

        $builder->where('is_active', true);

        if ($className !== null) {
            $builder->where('class_name', $className);
        }

        if ($systemSpecimen !== null) {
            $builder->where('system_specimen', 'like', "%{$systemSpecimen}%");
        }

        $cleanQuery = trim($query);

        if ($cleanQuery !== '') {
            $builder->where(function ($sub) use ($cleanQuery) {
                $sub->where('loinc_num', $cleanQuery)
                    ->orWhere('component', 'like', "%{$cleanQuery}%")
                    ->orWhere('method_type', 'like', "%{$cleanQuery}%");
            });
        }

        return $builder->orderBy('component', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Mencocokkan nama tes klinis ke item LOINC kanonikal terbaik.
     */
    public function matchBestLoinc(string $testName, string $sampleType = 'Ser/Plas'): ?LaboratoryLoincCatalog
    {
        // 1. Coba pencarian persis
        $exact = LaboratoryLoincCatalog::where('component', $testName)
            ->where('is_active', true)
            ->first();

        if ($exact) {
            return $exact;
        }

        // 2. Coba pencarian berbasis token
        $tokens = explode(' ', trim($testName));
        $primaryToken = $tokens[0] ?? $testName;

        return LaboratoryLoincCatalog::where('component', 'like', "%{$primaryToken}%")
            ->where('is_active', true)
            ->orderBy('is_orderable', 'desc')
            ->first();
    }
}
