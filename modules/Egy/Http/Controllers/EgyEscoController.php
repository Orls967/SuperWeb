<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Egy\Application\Services\DistrictUtilitiesManagementService;
use Modules\Egy\Domain\Models\EscoContract;

final class EgyEscoController extends Controller
{
    public function __construct(
        private readonly DistrictUtilitiesManagementService $utilitiesService
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->utilitiesService->getEscoOverview();

        return view('egy::esco.index', $overview);
    }

    public function show(Request $request, string $id): View
    {
        $contract = EscoContract::with('settlements')->findOrFail((int) $id);

        $user = $request->user();
        if ($user !== null && ! ($user->hasRole('admin') || $user->hasRole('energy_auditor') || $user->hasRole('energy_billing'))) {
            // Anti-IDOR: hanya klien pemilik yang boleh mengakses
            $partyId = (string) ($user->party_id ?? $user->id);
            if ((string) $contract->client_party_id !== (string) $user->id && (string) $contract->client_party_id !== $partyId) {
                abort(403, 'Anda tidak berhak mengakses rincian kontrak ESCO ini');
            }
        }

        return view('egy::esco.show', [
            'contract' => $contract,
        ]);
    }
}
