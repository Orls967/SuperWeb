<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\MaskRule;
use Modules\Platform\Domain\ValueObjects\MaskingSpecification;

/**
 * Repository aturan penyamaran data PII berbasis database plt_mask_rules.
 */
class MaskRuleRepository
{
    /**
     * @return list<MaskingSpecification>
     */
    public function getActiveRulesForDomain(string $domain): array
    {
        $records = MaskRule::where('domain', $domain)
            ->where('is_active', true)
            ->get();

        $specs = [];
        foreach ($records as $r) {
            $specs[] = new MaskingSpecification(
                domain: $r->domain,
                columnPattern: $r->column_pattern,
                strategy: $r->mask_strategy
            );
        }

        return $specs;
    }

    public function registerRule(string $domain, string $columnPattern, string $strategy): MaskRule
    {
        return MaskRule::updateOrCreate(
            ['domain' => $domain, 'column_pattern' => $columnPattern],
            ['mask_strategy' => $strategy, 'is_active' => true]
        );
    }
}

