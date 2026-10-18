<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\DTOs\Resilience\DataSovereigntyCheckDto;
use Modules\Core\Application\DTOs\Resilience\DrillExecutionDto;
use Modules\Core\Application\DTOs\Resilience\KmsRotationDto;
use Modules\Core\Application\Services\Resilience\DataSovereigntyComplianceService;
use Modules\Core\Application\Services\Resilience\DisasterRecoveryDrillService;
use Modules\Core\Application\Services\Resilience\MultiRegionResilienceEngineService;
use Modules\Core\Domain\Models\CoreDataSovereigntyPolicy;
use Modules\Core\Domain\Models\CoreDrDrillExecution;
use Modules\Core\Domain\Models\CoreKmsKeyRotation;

class ResilienceGovernanceController extends Controller
{
    public function index(
        MultiRegionResilienceEngineService $resilienceService,
        DisasterRecoveryDrillService $drillService,
        DataSovereigntyComplianceService $complianceService
    ): View {
        $topology = $resilienceService->getReplicationTopology();
        $drills = CoreDrDrillExecution::with('user')->latest()->take(10)->get();
        $kmsKeys = CoreKmsKeyRotation::with('user')->latest()->take(10)->get();
        $policies = CoreDataSovereigntyPolicy::all();

        return view('core::resilience.index', compact('topology', 'drills', 'kmsKeys', 'policies'));
    }

    public function executeDrill(
        Request $request,
        DisasterRecoveryDrillService $drillService
    ): RedirectResponse {
        $validated = $request->validate([
            'drill_type' => ['required', 'string', 'max:40'],
            'primary_region' => ['required', 'string', 'max:30'],
            'target_dr_region' => ['required', 'string', 'max:30'],
            'simulated_lag_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $dto = new DrillExecutionDto(
            drillType: $validated['drill_type'],
            primaryRegion: $validated['primary_region'],
            targetDrRegion: $validated['target_dr_region'],
            simulatedLagSeconds: (int) ($validated['simulated_lag_seconds'] ?? 10),
            userId: $request->user()?->id,
            metadata: ['initiated_via' => 'admin_governance_ui']
        );

        $drill = $drillService->executeDrill($dto);

        return redirect()->route('admin.resilience.index')
            ->with('success', "Disaster Recovery Drill '{$drill->drill_uuid}' executed successfully (RPO: {$drill->rpo_seconds}s, RTO: {$drill->rto_seconds}s).");
    }

    public function rotateKms(
        Request $request,
        DataSovereigntyComplianceService $complianceService
    ): RedirectResponse {
        $validated = $request->validate([
            'key_alias' => ['required', 'string', 'max:64'],
            'encryption_algorithm' => ['required', 'string', 'max:50'],
            'rotation_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $dto = new KmsRotationDto(
            keyAlias: $validated['key_alias'],
            encryptionAlgorithm: $validated['encryption_algorithm'],
            rotationReason: $validated['rotation_reason'] ?? 'Manual admin rotation cycle',
            userId: $request->user()?->id
        );

        $key = $complianceService->rotateKmsKey($dto);

        return redirect()->route('admin.resilience.index')
            ->with('success', "KMS key '{$key->key_alias}' rotated successfully to Version {$key->key_version}. Fingerprint: {$key->key_fingerprint}");
    }

    public function checkResidency(
        Request $request,
        DataSovereigntyComplianceService $complianceService
    ): RedirectResponse {
        $validated = $request->validate([
            'jurisdiction_code' => ['required', 'string', 'max:10'],
            'data_type' => ['required', 'string', 'max:64'],
            'storage_region' => ['required', 'string', 'max:30'],
            'contains_pii' => ['nullable', 'boolean'],
            'is_cross_border_transfer' => ['nullable', 'boolean'],
        ]);

        $dto = new DataSovereigntyCheckDto(
            jurisdictionCode: $validated['jurisdiction_code'],
            dataType: $validated['data_type'],
            storageRegion: $validated['storage_region'],
            containsPii: (bool) ($validated['contains_pii'] ?? false),
            isCrossBorderTransfer: (bool) ($validated['is_cross_border_transfer'] ?? false)
        );

        $result = $complianceService->evaluateResidency($dto);

        return redirect()->route('admin.resilience.index')
            ->with('residency_result', $result);
    }
}
