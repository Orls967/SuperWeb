<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Algorithms\BlueCarbonCreditEstimator;
use Modules\Marine\Domain\Models\BlueCarbonAssessment;
use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;
use Modules\Marine\Domain\Models\MangroveRestorationProject;

/**
 * Service restorasi mangrove pesisir dan estimasi/penerbitan Blue Carbon Credits (Fase 171).
 */
class BlueCarbonMangroveRestorationService
{
    protected BlueCarbonCreditEstimator $creditEstimator;

    public function __construct(BlueCarbonCreditEstimator $creditEstimator)
    {
        $this->creditEstimator = $creditEstimator;
    }

    /**
     * Daftarkan proyek restorasi mangrove pesisir / tambak silvofishery baru.
     *
     * @param  array<string, mixed>  $projectData
     */
    public function registerProject(array $projectData): MangroveRestorationProject
    {
        $projectCode = 'MNG-'.strtoupper(substr(hash('sha256', ($projectData['project_name'] ?? 'MANGROVE').microtime()), 0, 10));

        return MangroveRestorationProject::create([
            'project_code' => $projectCode,
            'project_name' => (string) ($projectData['project_name'] ?? 'Pesisir Lestari Silvofishery'),
            'coastal_location' => (string) ($projectData['coastal_location'] ?? 'Tarakan, Kalimantan Utara'),
            'area_hectares' => (float) ($projectData['area_hectares'] ?? 50.0),
            'target_trees_planted' => (int) ($projectData['target_trees_planted'] ?? 100000),
            'current_trees_survived' => (int) ($projectData['current_trees_survived'] ?? 85000),
            'dominant_species' => (string) ($projectData['dominant_species'] ?? 'Rhizophora mucronata'),
            'start_date' => (string) ($projectData['start_date'] ?? now()->subYears(2)->toDateString()),
            'certification_standard' => (string) ($projectData['certification_standard'] ?? 'VERRA_VM0033'),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Lakukan asesmen penyerapan karbon berkala.
     *
     * @param  array<string, mixed>  $samplingData
     */
    public function conductCarbonAssessment(int $projectId, array $samplingData): BlueCarbonAssessment
    {
        $project = MangroveRestorationProject::findOrFail($projectId);

        $species = (string) ($samplingData['species'] ?? 'Rhizophora');
        $meanDbh = (float) ($samplingData['mean_dbh_cm'] ?? 12.5);
        $density = (int) ($samplingData['tree_density_per_hectare'] ?? 1800);
        $soilDepth = (float) ($samplingData['soil_depth_cm'] ?? 100.0);
        $bulkDensity = (float) ($samplingData['soil_bulk_density_g_cm3'] ?? 0.85);
        $socPct = (float) ($samplingData['soil_organic_carbon_percentage'] ?? 3.2);

        $estimation = $this->creditEstimator->estimateBlueCarbon(
            $species,
            (float) $project->area_hectares,
            $meanDbh,
            $density,
            $soilDepth,
            $bulkDensity,
            $socPct
        );

        $assessmentCode = 'ASSESS-'.strtoupper(substr(hash('sha256', $project->project_code.microtime()), 0, 10));

        return BlueCarbonAssessment::create([
            'project_id' => $project->id,
            'assessment_code' => $assessmentCode,
            'assessment_date' => (string) ($samplingData['assessment_date'] ?? now()->toDateString()),
            'mean_dbh_cm' => $meanDbh,
            'tree_density_per_hectare' => $density,
            'soil_bulk_density_g_cm3' => $bulkDensity,
            'soil_organic_carbon_percentage' => $socPct,
            'aboveground_biomass_t_per_ha' => $estimation['aboveground_biomass_t_per_ha'],
            'belowground_biomass_t_per_ha' => $estimation['belowground_biomass_t_per_ha'],
            'soil_carbon_t_per_ha' => $estimation['soil_carbon_t_per_ha'],
            'total_carbon_stock_t_per_ha' => $estimation['total_carbon_stock_t_per_ha'],
            'gross_co2_equivalent_tons' => $estimation['gross_co2_equivalent_total_tons'],
            'net_marketable_credits_tco2e' => $estimation['net_marketable_carbon_credits_tco2e'],
            'accredited_auditor' => (string) ($samplingData['accredited_auditor'] ?? 'PT Sucofindo Verra Auditor'),
        ]);
    }

    /**
     * Terbitkan unit kredit karbon bersertifikat (Carbon Credit Issuance).
     */
    public function issueCarbonCredits(int $assessmentId, string $registryName = 'VERRA_REGISTRY'): BlueCarbonCreditIssuance
    {
        $assessment = BlueCarbonAssessment::findOrFail($assessmentId);

        if ($assessment->net_marketable_credits_tco2e <= 0.0) {
            throw new DomainException('Tidak ada kredit karbon yang dapat diterbitkan dari asesmen ini.');
        }

        $serialNumber = 'VCS-'.date('Y').'-'.strtoupper(substr(hash('sha256', $assessment->assessment_code.microtime()), 0, 16));

        return BlueCarbonCreditIssuance::create([
            'project_id' => $assessment->project_id,
            'assessment_id' => $assessment->id,
            'issuance_serial_number' => $serialNumber,
            'registry_name' => $registryName,
            'vintage_year' => (int) date('Y'),
            'credits_issued_tco2e' => (float) $assessment->net_marketable_credits_tco2e,
            'credits_retired_tco2e' => 0.0,
            'issuance_date' => now()->toDateString(),
            'status' => 'ISSUED_ACTIVE',
        ]);
    }
}
