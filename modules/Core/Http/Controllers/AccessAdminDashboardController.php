<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessPolicy;
use Modules\Core\Domain\Models\CoreAccessRecertificationCampaign;
use Modules\Core\Domain\Models\CoreIdentityLink;

class AccessAdminDashboardController extends Controller
{
    public function grants(Request $request): View
    {
        $status = $request->query('status');
        $query = CoreAccessGrant::with(['user', 'tenant'])->latest('id');

        if ($status !== null && trim((string) $status) !== '') {
            $query->where('status', (string) $status);
        }

        $grants = $query->paginate(25);

        return view('core::admin.access.grants', compact('grants', 'status'));
    }

    public function policies(Request $request): View
    {
        $policies = CoreAccessPolicy::latest('id')->paginate(25);

        return view('core::admin.access.policies', compact('policies'));
    }

    public function identity(Request $request): View
    {
        $links = CoreIdentityLink::with('tenant')->latest('id')->paginate(25);

        return view('core::admin.access.identity', compact('links'));
    }

    public function campaigns(Request $request): View
    {
        $campaigns = CoreAccessRecertificationCampaign::withCount('items')->latest('id')->paginate(25);

        return view('core::admin.access.campaigns', compact('campaigns'));
    }
}
