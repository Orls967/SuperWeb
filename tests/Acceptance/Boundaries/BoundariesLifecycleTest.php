<?php

declare(strict_types=1);

namespace Tests\Acceptance\Boundaries;

use App\Models\User;
use App\Quality\Audit\Fixtures\BoundaryAuditCorruptionFixture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Boundaries\BoundaryOutboxPublisher;
use Modules\Core\Application\Boundaries\ExpireWaiversAction;
use Modules\Core\Application\Boundaries\TransitionViolationAction;
use Modules\Core\Application\Boundaries\TriageViolationAction;
use Modules\Core\Application\Boundaries\VerifyViolationAction;
use Modules\Core\Application\Boundaries\WaiverApprovalBridge;
use Modules\Core\Application\Boundaries\WaiveViolationAction;
use Modules\Core\Contracts\VehicleDirectory;
use Modules\Core\Contracts\VehicleModelResolver;
use Modules\Core\Contracts\VehicleSearchCriteria;
use Modules\Core\Contracts\VehicleSnapshot;
use Modules\Core\Domain\Algorithms\Boundaries\BlastRadiusCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\ContractCoverageScorer;
use Modules\Core\Domain\Algorithms\Boundaries\CoverageCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\CrossModuleBlastRadiusAnalyzer;
use Modules\Core\Domain\Algorithms\Boundaries\DependencyCondensationGraph;
use Modules\Core\Domain\Algorithms\Boundaries\PrefixCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\SccCertificateVerifier;
use Modules\Core\Domain\Algorithms\Boundaries\StronglyConnectedComponents;
use Modules\Core\Domain\Algorithms\Boundaries\TablePrefixOwnershipResolver;
use Modules\Core\Domain\Algorithms\Boundaries\TopologicalCertificateVerifier;
use Modules\Core\Domain\Enums\ViolationCategory;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Tests\TestCase;

class BoundariesLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Access control and unauthorized rejection on boundary management routes.
     */
    public function test_boundary_management_routes_authorization_and_idor_protection(): void
    {
        // 1. Unauthenticated guest is redirected to login
        $response = $this->get(route('admin.boundaries.map'));
        $response->assertRedirect(route('login'));

        // 2. Unauthorized role (e.g. customer) is rejected with 403 Forbidden
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);

        $this->get(route('admin.boundaries.map'))->assertForbidden();
        $this->get(route('admin.boundaries.violations'))->assertForbidden();
        $this->get(route('admin.boundaries.waivers'))->assertForbidden();
        $this->get(route('admin.boundaries.export.violations'))->assertForbidden();

        // 3. Authorized role (admin or platform_architect) has access (200 OK)
        $architect = User::factory()->create(['role' => 'admin']);
        $this->actingAs($architect);

        $this->get(route('admin.boundaries.map'))->assertOk();
        $this->get(route('admin.boundaries.violations'))->assertOk();
        $this->get(route('admin.boundaries.waivers'))->assertOk();
        $this->get(route('admin.boundaries.export.violations'))->assertOk();
    }

    /**
     * Test 2: State machine lifecycle transitions, guards, idempotency, and waiver expiration.
     */
    public function test_boundary_violation_state_machine_lifecycle_and_idempotency(): void
    {
        $architect = User::factory()->create(['role' => 'admin']);

        // 1. Create a detected violation
        $violation = CoreBoundaryViolation::create([
            'rule_id' => 'A1',
            'category' => ViolationCategory::DOMAIN_IMPORT->value,
            'file_path' => 'modules/AutoServe/Domain/Models/Booking.php',
            'line_number' => 17,
            'signature' => 'use Modules\Core\Domain\Models\Vehicle',
            'client_module' => 'AutoServe',
            'owner_module' => 'Core',
            'status' => ViolationStatus::DETECTED->value,
        ]);

        $this->assertEquals(ViolationStatus::DETECTED, $violation->status);

        // 2. Triage action: detected -> triaged
        $triageAction = app(TriageViolationAction::class);
        $violation = $triageAction->execute($violation, (string) $architect->id, 'AutoServe booking coupling triage');
        $this->assertEquals(ViolationStatus::TRIAGED, $violation->status);

        // 3. Transition: triaged -> contract_designed
        $transitionAction = app(TransitionViolationAction::class);
        $violation = $transitionAction->execute(
            $violation,
            ViolationStatus::CONTRACT_DESIGNED,
            (string) $architect->id,
            'Designed VehicleDirectory and VehicleModelResolver contracts',
            [
                'contract_name' => 'Modules\Core\Contracts\VehicleDirectory',
                'adr_anchor' => 'docs/DECISIONS.md#w8-d44',
            ]
        );
        $this->assertEquals(ViolationStatus::CONTRACT_DESIGNED, $violation->status);

        // 4. Transition: contract_designed -> migrating
        $violation = $transitionAction->execute(
            $violation,
            ViolationStatus::MIGRATING,
            (string) $architect->id,
            'Migrating call sites in AutoServe'
        );
        $this->assertEquals(ViolationStatus::MIGRATING, $violation->status);

        // 5. Verify action: migrating -> verified
        $verifyAction = app(VerifyViolationAction::class);
        $violation = $verifyAction->execute(
            $violation,
            (string) $architect->id,
            'Removed from baseline and unit test green'
        );
        $this->assertEquals(ViolationStatus::VERIFIED, $violation->status);

        // 6. Transition: verified -> closed
        $violation = $transitionAction->execute(
            $violation,
            ViolationStatus::CLOSED,
            (string) $architect->id,
            'arch:scan is clean for this entry on master commit',
            ['commit_sha' => '17d9bac88ba62b5fdd3169c11ef7a156d6d6917e']
        );
        $this->assertEquals(ViolationStatus::CLOSED, $violation->status);

        // Guard Check: Invalid transition throws exception
        try {
            $transitionAction->execute($violation, ViolationStatus::DETECTED, (string) $architect->id, 'Illegal jump');
            $this->fail('Expected DomainException for illegal transition from closed to detected');
        } catch (\DomainException) {
            // Expected
        }

        // 7. Waiver Branch Lifecycle & Expiration: triaged -> waived -> expired to triaged
        $waiverViolation = CoreBoundaryViolation::create([
            'rule_id' => 'A2',
            'category' => ViolationCategory::TABLE_PREFIX->value,
            'file_path' => 'modules/Pricing/database/migrations/2026_10_07_810100_create_algorithmic_pricing_tables.php',
            'line_number' => 12,
            'signature' => "Schema::create('prc_price_ticks')",
            'client_module' => 'Pricing',
            'owner_module' => 'Procurement',
            'status' => ViolationStatus::TRIAGED->value,
        ]);

        $waiveAction = app(WaiveViolationAction::class);
        $waiverViolation = $waiveAction->execute(
            $waiverViolation,
            (string) $architect->id,
            'Legacy pricing tables waiver prior to harmonized pric_ prefix',
            'docs/DECISIONS.md#w8-d43',
            now()->addDays(30)
        );

        $this->assertEquals(ViolationStatus::WAIVED, $waiverViolation->status);
        $this->assertEquals('docs/DECISIONS.md#w8-d43', $waiverViolation->adr_anchor);
        $this->assertNotNull($waiverViolation->expires_at);

        // Simulate time passing: set expires_at in the past
        $waiverViolation->update(['expires_at' => now()->subDay()]);

        // Expire action
        $expireAction = app(ExpireWaiversAction::class);
        $expiredCount = $expireAction->execute();

        $this->assertGreaterThanOrEqual(1, $expiredCount);
        $waiverViolation->refresh();
        $this->assertEquals(ViolationStatus::TRIAGED, $waiverViolation->status);

        // 8. Integrasi ApprovalEngine, TaskInbox & OutboxBus (DR4-7b, DR4-7d)
        $approvalBridge = app(WaiverApprovalBridge::class);
        $task = $approvalBridge->notifyUpcomingExpiration($waiverViolation, 3, $architect);
        $this->assertNotNull($task);
        $this->assertEquals('urgent', $task->priority);
        $this->assertEquals('platform_architect', $task->assignee_role);

        $outboxPublisher = app(BoundaryOutboxPublisher::class);
        $outboxMsg = $outboxPublisher->publishWaiverExpired($waiverViolation);
        $this->assertNotNull($outboxMsg);
    }

    /**
     * Test 3: Vehicle domain contracts migration seamless interoperability.
     */
    public function test_vehicle_domain_contracts_migration_seamless_interoperability(): void
    {
        $user = User::factory()->create();

        // 1. Create a vehicle using VehicleModelResolver
        $vehicle = VehicleModelResolver::create([
            'user_id' => $user->id,
            'plate_number' => 'B 1234 W8P4',
            'vin' => 'VIN-W8P4-CONTRACT-TEST',
            'color' => 'Midnight Blue',
            'odometer_km' => 15000,
            'status' => 'active',
        ]);

        $this->assertNotNull($vehicle->id);
        $this->assertEquals('B 1234 W8P4', $vehicle->plate_number);

        // 2. Query through VehicleDirectory contract
        $directory = app(VehicleDirectory::class);
        $snapshot = $directory->findById($vehicle->id);

        $this->assertInstanceOf(VehicleSnapshot::class, $snapshot);
        $this->assertEquals($vehicle->id, $snapshot->id);
        $this->assertEquals('B 1234 W8P4', $snapshot->plateNumber);
        $this->assertEquals(15000, $snapshot->odometerKm);
        $this->assertEquals('active', $snapshot->status);

        // 3. Query by plate number
        $plateSnapshot = $directory->findByPlateNumber('B 1234 W8P4');
        $this->assertNotNull($plateSnapshot);
        $this->assertEquals($vehicle->id, $plateSnapshot->id);

        // 4. Query snapshots by IDs
        $snapshots = $directory->snapshotsByIds([$vehicle->id]);
        $this->assertCount(1, $snapshots);
        $this->assertEquals($vehicle->id, $snapshots[0]->id);

        // 5. Query through VehicleModelResolver for Eloquent relations
        $resolvedVehicle = VehicleModelResolver::findOrFail($vehicle->id);
        $this->assertInstanceOf(Model::class, $resolvedVehicle);
        $this->assertEquals($user->id, $resolvedVehicle->user_id);

        // 6. Search criteria and fleet summary through contract
        $searchResult = $directory->search(new VehicleSearchCriteria(
            plateNumberPattern: '1234',
            statuses: ['active']
        ));
        $this->assertCount(1, $searchResult);
        $this->assertEquals($vehicle->id, $searchResult[0]->id);

        $summary = $directory->getFleetSummary();
        $this->assertGreaterThanOrEqual(1, $summary->totalVehicles);
        $this->assertGreaterThanOrEqual(1, $summary->activeVehicles);
    }

    /**
     * Test 4: Algorithms verification — Tarjan SCC, Prefix Ownership, and Contract Coverage Scorer.
     */
    public function test_boundary_algorithms_tarjan_scc_prefix_ownership_and_contract_coverage(): void
    {
        // 1. Tarjan SCC & SccCertificateVerifier
        $nodes = ['AutoServe', 'Core', 'Logistics', 'Mall', 'Store'];
        $acyclicGraph = [
            'AutoServe' => ['Core'],
            'Logistics' => ['Core'],
            'Mall' => ['Core'],
            'Store' => ['Core'],
            'Core' => [],
        ];

        $tarjan = new StronglyConnectedComponents;
        $result = $tarjan->compute($nodes, $acyclicGraph);

        $this->assertFalse($result['hasCycles']);
        $this->assertCount(5, $result['components']); // each node is its own SCC

        $sccVerifier = new SccCertificateVerifier;
        $this->assertTrue($sccVerifier->verify($result['components'], $result['condensationDag'], $nodes));

        // Test cycle detection
        $cyclicGraph = $acyclicGraph;
        $cyclicGraph['Core'] = ['AutoServe']; // Introduce forbidden cycle: AutoServe -> Core -> AutoServe
        $cyclicResult = $tarjan->compute($nodes, $cyclicGraph);
        $this->assertTrue($cyclicResult['hasCycles']);

        // 2. TablePrefixOwnershipResolver & PrefixCertificateVerifier
        $prefixes = [
            'hcm_' => 'Hcm',
            'hcm_pay_' => 'Hcm', // nested prefix
            'prc_' => 'Procurement',
            'pric_' => 'Pricing',
            'ven_' => 'Venue',
            'vnd_' => 'Vending',
            'core_' => 'Core',
        ];

        $legacyTables = ['users' => 'App'];
        $sharedTables = ['Pricing' => ['prc_frozen_quotes']];

        $resolver = new TablePrefixOwnershipResolver($prefixes, $legacyTables, $sharedTables);

        // Check longest prefix matching
        $hcmPayRes = $resolver->resolve('hcm_pay_structures', 'Hcm');
        $this->assertEquals('Hcm', $hcmPayRes->owner);
        $this->assertTrue($hcmPayRes->isAllowed);
        $this->assertEquals('hcm_pay_', $hcmPayRes->matchedPrefix);

        $prcRes = $resolver->resolve('prc_purchase_orders', 'Procurement');
        $this->assertEquals('Procurement', $prcRes->owner);
        $this->assertTrue($prcRes->isAllowed);

        // Check shared table access
        $pricingShared = $resolver->resolve('prc_frozen_quotes', 'Pricing');
        $this->assertEquals('Procurement', $pricingShared->owner);
        $this->assertTrue($pricingShared->isAllowed); // allowed via shared_tables waiver

        // Check illegal foreign access
        $illegalAccess = $resolver->resolve('prc_quotes', 'AutoServe');
        $this->assertEquals('Procurement', $illegalAccess->owner);
        $this->assertFalse($illegalAccess->isAllowed);

        $prefixVerifier = new PrefixCertificateVerifier;
        $this->assertTrue($prefixVerifier->verifyPrefixes($prefixes));

        // 3. ContractCoverageScorer & CoverageCertificateVerifier
        $contractCalls = [
            'AutoServe:Core' => 8,
            'Logistics:Core' => 4,
            'Mall:Core' => 12,
            'Store:Core' => 16,
        ];
        $violations = [
            // 2 remaining violations
            ['client' => 'AutoServe', 'owner' => 'Core'],
            ['client' => 'Store', 'owner' => 'Core'],
        ];

        $scorer = new ContractCoverageScorer;
        $scoreResult = $scorer->calculate($contractCalls, $violations);

        // Total contracts = 40, total violations = 2 -> total = 42
        // Global score = floor(40 * 1000 / 42) = 952 permille
        $this->assertIsInt($scoreResult['globalScore']);
        $this->assertEquals(952, $scoreResult['globalScore']);
        $this->assertGreaterThanOrEqual(0, $scoreResult['globalScore']);
        $this->assertLessThanOrEqual(1000, $scoreResult['globalScore']);

        $coverageVerifier = new CoverageCertificateVerifier;
        $this->assertTrue($coverageVerifier->verify($scoreResult));

        // 4. CrossModuleBlastRadiusAnalyzer & BlastRadiusCertificateVerifier
        $blastAnalyzer = new CrossModuleBlastRadiusAnalyzer;
        $blastReport = $blastAnalyzer->analyze('Core', $acyclicGraph, ['Core' => 950]);

        $this->assertEquals('Core', $blastReport['target']);
        $this->assertCount(4, $blastReport['directConsumers']);
        $this->assertGreaterThanOrEqual(0, $blastReport['impactScorePermille']);
        $this->assertLessThanOrEqual(1000, $blastReport['impactScorePermille']);

        $blastVerifier = new BlastRadiusCertificateVerifier;
        $this->assertTrue($blastVerifier->verify($blastReport, $acyclicGraph));

        // 5. DependencyCondensationGraph & TopologicalCertificateVerifier
        $condensationGraph = new DependencyCondensationGraph;
        $graphAnalysis = $condensationGraph->analyze($nodes, $acyclicGraph);

        $this->assertTrue($graphAnalysis['isDag']);
        $this->assertCount(5, $graphAnalysis['topologicalOrder']);
        $this->assertContains('Core', $graphAnalysis['leafModules']); // out-degree 0

        $topoVerifier = new TopologicalCertificateVerifier;
        $this->assertTrue($topoVerifier->verify($nodes, $acyclicGraph, $graphAnalysis['topologicalOrder']));
    }

    /**
     * Test 5: Dual-source boundary audit and corruption fixture detection.
     */
    public function test_dual_source_boundary_audit_and_corruption_fixture_detection(): void
    {
        // 0. Synchronize scanned state into core_boundary_violations
        Artisan::call('boundary:triage');

        // 1. Verify clean audit execution
        $exitCode = Artisan::call('boundary:audit');
        $output = Artisan::output();
        $this->assertEquals(0, $exitCode, "boundary:audit harus lulus pada kondisi bersih:\n{$output}");

        // 2. Corrupt data using BoundaryAuditCorruptionFixture
        $fixture = new BoundaryAuditCorruptionFixture;
        $corruptionDescription = $fixture->corrupt();
        $this->assertNotEmpty($corruptionDescription);

        // 3. boundary:audit MUST fail on corrupted state
        $corruptExitCode = Artisan::call('boundary:audit');
        $corruptOutput = Artisan::output();
        $this->assertEquals(1, $corruptExitCode, 'boundary:audit harus mendeteksi korupsi');
        $this->assertStringContainsString('AUDIT_FAIL', $corruptOutput);

        // 4. Restore state
        $fixture->restore();

        // 5. boundary:audit passes cleanly again
        $restoredExitCode = Artisan::call('boundary:audit');
        $this->assertEquals(0, $restoredExitCode, 'boundary:audit harus kembali hijau setelah fixture dipulihkan');
    }
}
