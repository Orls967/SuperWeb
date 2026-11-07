<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Domain\Models\MiningSmelterQualityAssay;

final class ConductSmelterQualityAssayAction
{
    /**
     * @param  array<string, mixed>  $specs
     */
    public function execute(
        MiningSmelterFurnaceRun $run,
        array $specs = []
    ): MiningSmelterQualityAssay {
        $primaryNiGrade = (float) ($specs['primary_metal_grade_pct'] ?? 12.80);
        if ($primaryNiGrade <= 0.0 || $primaryNiGrade > 100.0) {
            throw new InvalidArgumentException('Kadar nikel produk harus berada di rentang 0% - 100%.');
        }

        $assayNumber = $specs['assay_number'] ?? ('ASY-'.$run->run_code);
        $meetsLme = $primaryNiGrade >= 10.0;

        $signature = hash('sha256', sprintf(
            'ASSAY:%s:%.3f:%.3f:%s',
            $assayNumber,
            $primaryNiGrade,
            (float) ($specs['iron_fe_pct'] ?? 80.50),
            $meetsLme ? '1' : '0'
        ));

        return MiningSmelterQualityAssay::updateOrCreate(
            ['assay_number' => $assayNumber],
            [
                'furnace_run_id' => $run->id,
                'sample_code' => $specs['sample_code'] ?? ('SMP-'.$run->run_code),
                'metal_commodity' => $specs['metal_commodity'] ?? 'NPI',
                'primary_metal_grade_pct' => $primaryNiGrade,
                'secondary_metal_grade_pct' => (float) ($specs['secondary_metal_grade_pct'] ?? 0.0),
                'iron_fe_pct' => (float) ($specs['iron_fe_pct'] ?? 80.50),
                'carbon_c_pct' => (float) ($specs['carbon_c_pct'] ?? 2.10),
                'silicon_si_pct' => (float) ($specs['silicon_si_pct'] ?? 1.80),
                'phosphorus_p_pct' => (float) ($specs['phosphorus_p_pct'] ?? 0.025),
                'sulfur_s_pct' => (float) ($specs['sulfur_s_pct'] ?? 0.035),
                'moisture_pct' => (float) ($specs['moisture_pct'] ?? 0.05),
                'meets_lme_specification' => $meetsLme,
                'chief_chemist' => $specs['chief_chemist'] ?? 'Chief Smelter Chemist',
                'analyzed_at' => now(),
                'hmac_signature' => $signature,
                'status' => 'CERTIFIED',
                'metadata' => $specs['metadata'] ?? null,
            ]
        );
    }
}
