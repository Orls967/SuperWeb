<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\TransferPricingDocument;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Psr\Clock\ClockInterface;

class GenerateTpDocumentationAction
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    public function execute(
        string $entityCode,
        string $fiscalYear,
        string $documentType = 'LOCAL_FILE'
    ): TransferPricingDocument {
        $entity = IntercompanyEntity::where('entity_code', $entityCode)->first();
        if (! $entity) {
            throw new InvalidArgumentException("Entitas [{$entityCode}] tidak ditemukan.");
        }

        $rules = TransferPricingRule::where('is_active', true)->get();

        $farAnalysis = [
            'entity_code' => $entityCode,
            'characterization' => $entity->is_parent ? 'Principal & Shared Services' : 'Contract Manufacturer / Distributor',
            'functions' => [
                'manufacturing' => ! $entity->is_parent,
                'distribution' => ! $entity->is_parent,
                'corporate_governance' => $entity->is_parent,
                'treasury_management' => $entity->is_parent,
            ],
            'assets' => [
                'plant_and_machinery' => ! $entity->is_parent,
                'intellectual_property' => $entity->is_parent,
                'working_capital' => true,
            ],
            'risks' => [
                'market_risk' => $entity->is_parent ? 'HIGH' : 'LOW',
                'credit_risk' => 'MEDIUM',
                'foreign_exchange_risk' => 'HEDGED',
            ],
        ];

        $benchmarkSummary = [];
        foreach ($rules as $rule) {
            $benchmarkSummary[] = [
                'category' => $rule->product_category,
                'method' => $rule->tp_method,
                'q1_bps' => $rule->min_margin_bps ?: 600,
                'median_bps' => $rule->median_margin_bps ?: 1000,
                'q3_bps' => $rule->max_margin_bps ?: 1400,
                'source' => $rule->benchmark_industry_source,
            ];
        }

        $now = $this->clock !== null ? $this->clock->now() : new \DateTimeImmutable;
        $hashData = json_encode([$entityCode, $fiscalYear, $documentType, $farAnalysis, $benchmarkSummary]);

        return TransferPricingDocument::create([
            'document_type' => strtoupper($documentType),
            'fiscal_year' => $fiscalYear,
            'entity_id' => $entity->id,
            'far_analysis_json' => json_encode($farAnalysis, JSON_PRETTY_PRINT),
            'benchmark_summary_json' => json_encode($benchmarkSummary, JSON_PRETTY_PRINT),
            'status' => 'draft',
            'sha256_hash' => hash('sha256', (string) $hashData),
            'generated_at' => $now,
        ]);
    }
}
