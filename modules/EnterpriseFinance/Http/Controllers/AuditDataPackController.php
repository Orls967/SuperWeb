<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Application\Services\AuditDataPackGeneratorService;

class AuditDataPackController extends Controller
{
    public function __construct(
        protected readonly AuditDataPackGeneratorService $dataPackService
    ) {}

    public function index(Request $request): View
    {
        return view('enterprise_finance::audit_pack', [
            'recentPacks' => [],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entity_code' => ['required', 'string'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $pack = $this->dataPackService->generateAuditPack(
            entityCode: $validated['entity_code'],
            period: $validated['period']
        );

        return redirect()->route('enterprise_finance.audit_pack')
            ->with('status', "Paket data bukti audit berhasil dibuat: {$pack['zip_path']}");
    }
}

