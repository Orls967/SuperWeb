<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Enums\CatRunStatus;
use Modules\Insurance\Domain\Models\CatEvent;
use Modules\Insurance\Domain\Models\CatExposure;
use Modules\Insurance\Domain\Models\CatPlan;
use Modules\Insurance\Domain\Models\CatRun;
use Modules\Insurance\Domain\Models\CatYearLoss;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Shared\Domain\Algorithms\Catastrophe\CatPerilParams;
use Modules\Shared\Domain\Algorithms\Catastrophe\EventCatalogCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Catastrophe\EventCatalogGenerator;
use Modules\Shared\Domain\Algorithms\Catastrophe\ExceedanceCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Catastrophe\ExceedanceCurve;
use Modules\Shared\Domain\Algorithms\Chain\CanonicalHashChain;

final class CatModelingService
{
    public function __construct(
        private readonly EventCatalogGenerator $catalogGenerator,
        private readonly EventCatalogCertificateVerifier $catalogVerifier,
        private readonly ExceedanceCurve $curve,
        private readonly ExceedanceCertificateVerifier $curveVerifier,
        private readonly ParameterRepository $parameters,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Jalankan simulasi CAT penuh dan simpan hasil bersertifikat.
     */
    public function run(string $portfolioCode, int $seed, int $years = 250, int $eventsPerPeril = 10, ?int $actorId = null): CatRun
    {
        $runCode = sprintf('CAT-%s-%d-%d', $portfolioCode, $seed, $this->clock->now()->getTimestamp());

        // 1. Dapatkan peril params
        $perils = $this->loadPerilParams();
        $perilsByCode = [];
        foreach ($perils as $p) {
            $perilsByCode[$p->code] = $p;
        }

        // 2. Agregasi eksposur dari polis aktif
        [$exposuresByRegionPeril, $policyCounts] = $this->aggregateExposures();
        $regions = array_keys($exposuresByRegionPeril);
        if ($regions === []) {
            $regions = ['REG-JKT', 'REG-JBR', 'REG-JTM'];
            $exposuresByRegionPeril = [
                'REG-JKT' => ['EQ' => 50000000000, 'FL' => 30000000000],
                'REG-JBR' => ['EQ' => 30000000000, 'FL' => 20000000000],
                'REG-JTM' => ['EQ' => 20000000000, 'FL' => 15000000000],
            ];
            $policyCounts = [
                'REG-JKT' => ['EQ' => 50, 'FL' => 30],
                'REG-JBR' => ['EQ' => 30, 'FL' => 20],
                'REG-JTM' => ['EQ' => 20, 'FL' => 15],
            ];
        }

        $totalExposure = 0;
        foreach ($exposuresByRegionPeril as $perilMap) {
            $totalExposure += array_sum($perilMap);
        }

        // 3. Generate catalog deterministik (A5)
        $catalog = $this->catalogGenerator->generate($seed, $perils, $regions, $eventsPerPeril);
        $catalogHash = $this->catalogGenerator->catalogHash($catalog);

        // Verifikasi sertifikat katalog
        $this->catalogVerifier->verify($catalog, $catalogHash, $perilsByCode, $exposuresByRegionPeril);

        // 4. Simulasi Year Loss Table
        $simulationYears = max(10, $years);
        $yltRaw = $this->catalogGenerator->simulateYears($seed, $catalog, $exposuresByRegionPeril, $perilsByCode, $simulationYears);

        $yearLossesList = [];
        foreach ($yltRaw as $events) {
            $yearLossesList[] = array_column($events, 'loss_idr');
        }

        // 5. Kurva exceedance (A4)
        $curveResult = $this->curve->build($yearLossesList);
        $this->curveVerifier->verify($curveResult);

        $aal = $curveResult->aalIdr;
        $aepRp100 = $this->curve->aepAt($curveResult, min(100, $simulationYears));
        $aepRp250 = $this->curve->aepAt($curveResult, min(250, $simulationYears));
        $oepRp100 = $this->curve->oepAt($curveResult, min(100, $simulationYears));
        $tvarRp100 = $this->curve->tvarAt($curveResult, min(100, $simulationYears));

        $chain = new CanonicalHashChain;
        $exposureHash = $chain->link(CanonicalHashChain::GENESIS_HASH, $exposuresByRegionPeril);

        return DB::transaction(function () use (
            $runCode,
            $portfolioCode,
            $seed,
            $simulationYears,
            $eventsPerPeril,
            $catalogHash,
            $exposureHash,
            $totalExposure,
            $aal,
            $aepRp100,
            $aepRp250,
            $oepRp100,
            $tvarRp100,
            $actorId,
            $exposuresByRegionPeril,
            $catalog,
            $curveResult,
            $yltRaw,
            $yearLossesList
        ) {
            $run = CatRun::create([
                'run_code' => $runCode,
                'portfolio_code' => $portfolioCode,
                'seed' => $seed,
                'years' => $simulationYears,
                'events_per_peril' => $eventsPerPeril,
                'parameter_versions' => ['cat.perils' => 1],
                'catalog_hash' => $catalogHash,
                'exposure_hash' => $exposureHash,
                'total_exposure_idr' => $totalExposure,
                'aal_idr' => $aal,
                'aep_rp100_idr' => $aepRp100,
                'aep_rp250_idr' => $aepRp250,
                'oep_rp100_idr' => $oepRp100,
                'tvar_rp100_idr' => $tvarRp100,
                'status' => CatRunStatus::Certified,
                'created_by' => $actorId,
            ]);

            // Simpan eksposur
            foreach ($exposuresByRegionPeril as $reg => $perils) {
                foreach ($perils as $p => $exp) {
                    CatExposure::create([
                        'run_id' => $run->id,
                        'region_code' => $reg,
                        'peril' => $p,
                        'exposure_idr' => $exp,
                        'policy_count' => $policyCounts[$reg][$p] ?? 1,
                    ]);
                }
            }

            // Simpan sample events katalog (maksimal 100 agar ringan)
            $sampleCatalog = array_slice($catalog, 0, 100);
            foreach ($sampleCatalog as $ev) {
                CatEvent::create([
                    'run_id' => $run->id,
                    'event_no' => $ev['event_no'],
                    'peril' => $ev['peril'],
                    'region_code' => $ev['region'],
                    'intensity_bps' => $ev['intensity_bps'],
                    'annual_rate_ppm' => $ev['annual_rate_ppm'],
                ]);
            }

            // Simpan sample Year Loss
            for ($y = 0; $y < min(50, $simulationYears); $y++) {
                CatYearLoss::create([
                    'run_id' => $run->id,
                    'year_index' => $y + 1,
                    'aggregate_idr' => $curveResult->aggregates[$y],
                    'max_event_idr' => $curveResult->maxima[$y],
                    'events' => array_slice($yltRaw[$y] ?? [], 0, 10),
                ]);
            }

            // Simpan rencana proteksi perbandingan
            $this->buildProtectionPlans($run, $yearLossesList, $aepRp100, $aepRp250);

            return $run;
        });
    }

    private function buildProtectionPlans(CatRun $run, array $yearLossesList, int $aepRp100, int $aepRp250): void
    {
        // Struktur Plan 1: Konservatif (Attach 50M, Limit 150M)
        $attach1 = 50000000000;
        $limit1 = 150000000000;
        $ceded1 = $this->curve->catXolCededPerYear($yearLossesList, $attach1, $limit1, 1);
        $cededAal1 = (int) intdiv(array_sum($ceded1), max(1, count($ceded1)));
        CatPlan::create([
            'run_id' => $run->id,
            'structure_code' => 'PLAN-CONSERVATIVE',
            'attachment_idr' => $attach1,
            'limit_idr' => $limit1,
            'reinstatements' => 1,
            'layer_premium_idr' => (int) intdiv($limit1 * 500, 10000), // 5% rate on line
            'ceded_aal_idr' => $cededAal1,
            'net_pml_rp100_idr' => max(0, $aepRp100 - $limit1),
            'net_pml_rp250_idr' => max(0, $aepRp250 - $limit1),
            'net_cost_idr' => (int) intdiv($limit1 * 500, 10000) - $cededAal1,
        ]);

        // Struktur Plan 2: Agresif (Attach 100M, Limit 200M)
        $attach2 = 100000000000;
        $limit2 = 200000000000;
        $ceded2 = $this->curve->catXolCededPerYear($yearLossesList, $attach2, $limit2, 1);
        $cededAal2 = (int) intdiv(array_sum($ceded2), max(1, count($ceded2)));
        CatPlan::create([
            'run_id' => $run->id,
            'structure_code' => 'PLAN-AGGRESSIVE',
            'attachment_idr' => $attach2,
            'limit_idr' => $limit2,
            'reinstatements' => 1,
            'layer_premium_idr' => (int) intdiv($limit2 * 350, 10000), // 3.5% rate on line
            'ceded_aal_idr' => $cededAal2,
            'net_pml_rp100_idr' => max(0, $aepRp100 - $limit2),
            'net_pml_rp250_idr' => max(0, $aepRp250 - $limit2),
            'net_cost_idr' => (int) intdiv($limit2 * 350, 10000) - $cededAal2,
        ]);
    }

    /**
     * @return array{0: array<string, array<string, int>>, 1: array<string, array<string, int>>}
     */
    private function aggregateExposures(): array
    {
        $rows = InsurancePolicyPenuh::query()
            ->join('ins_full_products as p', 'p.id', '=', 'ins_full_policies.product_id')
            ->where('ins_full_policies.status', 'active')
            ->selectRaw('COALESCE(ins_full_policies.region_code, ?) as region, p.line_category, SUM(ins_full_policies.sum_insured_idr) as total_si, COUNT(*) as policy_cnt', ['REG-JKT'])
            ->groupBy('region', 'line_category')
            ->get();

        $exposures = [];
        $counts = [];
        foreach ($rows as $row) {
            $reg = (string) $row->region;
            $peril = match ($row->line_category) {
                'PROPERTY' => 'EQ',
                'VEHICLE' => 'FL',
                'MARINE_CARGO' => 'FL',
                default => 'EQ',
            };
            $exposures[$reg][$peril] = ($exposures[$reg][$peril] ?? 0) + (int) $row->total_si;
            $counts[$reg][$peril] = ($counts[$reg][$peril] ?? 0) + (int) $row->policy_cnt;
        }

        return [$exposures, $counts];
    }

    /**
     * @return list<CatPerilParams>
     */
    private function loadPerilParams(): array
    {
        return [
            new CatPerilParams(
                code: 'EQ',
                annualFrequencyPpm: 40000,
                minIntensityBps: 1000,
                maxIntensityBps: 10000,
                vulnerability: [
                    ['intensity_bps' => 1000, 'damage_bps' => 0],
                    ['intensity_bps' => 4000, 'damage_bps' => 1500],
                    ['intensity_bps' => 7000, 'damage_bps' => 5000],
                    ['intensity_bps' => 10000, 'damage_bps' => 9000],
                ]
            ),
            new CatPerilParams(
                code: 'FL',
                annualFrequencyPpm: 60000,
                minIntensityBps: 500,
                maxIntensityBps: 8000,
                vulnerability: [
                    ['intensity_bps' => 500, 'damage_bps' => 0],
                    ['intensity_bps' => 3000, 'damage_bps' => 2000],
                    ['intensity_bps' => 6000, 'damage_bps' => 6000],
                    ['intensity_bps' => 8000, 'damage_bps' => 8500],
                ]
            ),
        ];
    }
}
