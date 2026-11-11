<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Core\Application\Tenancy\TenantScopeRegistry;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreApiUsageRollup;
use Modules\Core\Domain\Models\CoreBulkImportJob;
use Modules\Core\Domain\Models\CoreEdiInterchange;
use Modules\Core\Domain\Models\CoreRetentionRun;
use Modules\Core\Domain\Models\CoreWebhook;

class ApiAdminDashboardController extends Controller
{
    public function clients(): View
    {
        $clients = CoreApiClient::with('keys')->orderByDesc('id')->get();

        return view('core::admin.api.clients', compact('clients'));
    }

    public function usage(): View
    {
        $rollups = CoreApiUsageRollup::with('client')->orderByDesc('bucket_hour')->limit(100)->get();

        return view('core::admin.api.usage', compact('rollups'));
    }

    public function edi(): View
    {
        $interchanges = CoreEdiInterchange::orderByDesc('id')->limit(100)->get();

        return view('core::admin.api.edi', compact('interchanges'));
    }

    public function imports(): View
    {
        $importJobs = CoreBulkImportJob::orderByDesc('id')->limit(100)->get();

        return view('core::admin.api.imports', compact('importJobs'));
    }

    public function webhooks(): View
    {
        $webhooks = CoreWebhook::orderByDesc('id')->get();

        return view('core::admin.api.webhooks', compact('webhooks'));
    }

    public function retention(): View
    {
        $runs = CoreRetentionRun::orderByDesc('id')->limit(100)->get();

        return view('core::admin.api.retention', compact('runs'));
    }

    public function developer(): View
    {
        return view('core::developer.index');
    }

    public function tenancy(TenantScopeRegistry $registry): View
    {
        $scopedModels = $registry->getRegisteredModels();

        return view('core::tenancy.index', compact('scopedModels'));
    }
}
