<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\DTOs\EpodSubmissionDto;
use Modules\Core\Application\DTOs\MobileFieldActionDto;
use Modules\Core\Application\DTOs\MobileSyncEventDto;
use Modules\Core\Application\Services\Mobile\MobileOfflineSyncEngineService;
use Modules\Core\Domain\Models\CoreMobileDevice;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;
use Modules\Core\Domain\Models\CoreMobileFieldAction;
use Modules\Core\Domain\Models\CoreMobileSyncEvent;

class MobileGovernanceController extends Controller
{
    public function __construct(
        protected MobileOfflineSyncEngineService $syncService
    ) {}

    public function index(): View
    {
        $stats = [
            'total_devices' => CoreMobileDevice::count(),
            'active_devices' => CoreMobileDevice::where('is_revoked', false)->count(),
            'total_sync_events' => CoreMobileSyncEvent::count(),
            'duplicates_prevented' => CoreMobileSyncEvent::where('conflict_status', 'SKIPPED_DUPLICATE')->count(),
            'total_epod' => CoreMobileEpodRecord::count(),
            'tampered_epod' => CoreMobileEpodRecord::where('is_tampered', true)->count(),
            'field_actions' => CoreMobileFieldAction::count(),
        ];

        $devices = CoreMobileDevice::latest()->take(10)->get();
        $recentSyncs = CoreMobileSyncEvent::latest()->take(10)->get();
        $recentEpods = CoreMobileEpodRecord::latest()->take(10)->get();
        $recentActions = CoreMobileFieldAction::latest()->take(10)->get();

        return view('core::mobile.index', compact(
            'stats',
            'devices',
            'recentSyncs',
            'recentEpods',
            'recentActions'
        ));
    }

    public function syncBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_uuid' => 'required|string|max:64',
            'role_scope' => 'required|string|in:operator,hub_operator,driver,agent',
            'event_type' => 'required|string|max:64',
            'reference_id' => 'required|string|max:64',
            'operator_name' => 'required|string|max:100',
            'details' => 'nullable|string',
        ]);

        $details = [];
        if (! empty($validated['details'])) {
            $decoded = json_decode($validated['details'], true);
            $details = is_array($decoded) ? $decoded : ['notes' => $validated['details']];
        }

        $dto = new MobileSyncEventDto(
            idempotencyKey: sprintf('mob:sync:%s:%s', $validated['device_uuid'], $validated['reference_id']),
            deviceUuid: $validated['device_uuid'],
            roleScope: $validated['role_scope'],
            eventType: $validated['event_type'],
            payload: array_merge($details, [
                'reference_id' => $validated['reference_id'],
                'operator_name' => $validated['operator_name'],
            ]),
            occurredOfflineAt: now()
        );

        $result = $this->syncService->processBatch([$dto]);

        $statusMsg = $result['applied'] > 0
            ? 'Event mutasi offline berhasil disinkronkan ke server.'
            : 'Event telah terproses sebelumnya (duplikasi diskip secara idempoten).';

        return redirect()->route('admin.mobile.index')->with('success', $statusMsg);
    }

    public function submitEpod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shipment_ref' => 'required|string|max:64',
            'driver_ref' => 'required|string|max:64',
            'recipient_name' => 'required|string|max:100',
            'recipient_role' => 'nullable|string|max:50',
            'signature_payload_b64' => 'required|string',
        ]);

        $dto = new EpodSubmissionDto(
            epodCode: 'EPOD-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4))),
            shipmentRef: $validated['shipment_ref'],
            driverRef: $validated['driver_ref'],
            recipientName: $validated['recipient_name'],
            recipientRole: $validated['recipient_role'] ?? 'Penerima',
            signaturePayloadB64: $validated['signature_payload_b64'],
            photoDataB64: 'data:image/jpeg;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            latitude: -6.2088,
            longitude: 106.8456,
            deliveredOfflineAt: now()
        );

        $record = $this->syncService->submitEpod($dto);

        return redirect()->route('admin.mobile.index')
            ->with('success', "e-POD {$record->epod_code} berhasil disubmit dengan verifikasi tanda tangan SHA-256 valid.");
    }

    public function recordAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_scope' => 'required|string|in:operator,hub_operator,driver,agent',
            'action_type' => 'required|string|max:50',
            'reference_id' => 'required|string|max:64',
            'operator_name' => 'required|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $dto = new MobileFieldActionDto(
            actionCode: 'ACT-'.strtoupper(bin2hex(random_bytes(6))),
            roleScope: $validated['role_scope'],
            actionType: $validated['action_type'],
            referenceId: $validated['reference_id'],
            operatorName: $validated['operator_name'],
            details: ['notes' => $validated['notes'] ?? 'Field action recorded'],
        );

        $record = $this->syncService->recordFieldAction($dto);

        return redirect()->route('admin.mobile.index')
            ->with('success', "Aksi lapangan {$record->action_code} untuk peran {$record->role_scope} berhasil dicatat.");
    }

    public function revokeDevice(CoreMobileDevice $device): RedirectResponse
    {
        $device->update(['is_revoked' => true]);

        return redirect()->route('admin.mobile.index')
            ->with('success', "Izin akses perangkat {$device->device_uuid} berhasil dibatalkan (revoked).");
    }
}
