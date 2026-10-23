<?php

declare(strict_types=1);

namespace Modules\Med\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Med\Application\Actions\BookProgrammaticAdInventoryCampaignAction;
use Modules\Med\Domain\Models\MedAdCampaign;
use Modules\Med\Domain\Models\MedDistributionChannel;

class MedAdCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = MedAdCampaign::query()->latest()->take(20)->get();
        $channels = MedDistributionChannel::query()->latest()->take(10)->get();

        return view('med::ads.index', compact('campaigns', 'channels'));
    }

    public function book(Request $request, BookProgrammaticAdInventoryCampaignAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'advertiser_entity_id' => ['required', 'string'],
            'channel_id' => ['required', 'string'],
            'target_impressions' => ['required', 'integer', 'min:1000'],
            'actual_cpm_minor' => ['nullable', 'integer'],
        ]);

        $campaign = $action->execute($validated);

        return redirect()->back()->with('success', "Kampanye Iklan Berhasil Dipesan [{$campaign->campaign_code}]: Total Rp ".number_format($campaign->total_spend_minor, 0, ',', '.'));
    }
}
