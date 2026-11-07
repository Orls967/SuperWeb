<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HtlPersonalizedTravelCampaign;
use Modules\Hotel\Domain\Models\HtlTravelPass;

final class GeneratePersonalizedTravelRecommendationsAction
{
    /**
     * @return array{member_tier: string, recommended_packages: list<array{destination: string, package_title: string, points_discounted: int, affinity_score: float}>}
     */
    public function execute(string $travelPassId): array
    {
        $pass = HtlTravelPass::findOrFail($travelPassId);

        $packages = [
            [
                'destination' => 'PROP-BALI-01',
                'package_title' => 'Nusantara Luxury Beach & Sunset Festival Escape',
                'points_discounted' => 35000,
                'affinity_score' => 0.9400,
            ],
            [
                'destination' => 'PROP-LBN-02',
                'package_title' => 'Komodo Island Catamaran Cruise & Private Villa Retreat',
                'points_discounted' => 48000,
                'affinity_score' => 0.8800,
            ],
            [
                'destination' => 'PROP-JOG-03',
                'package_title' => 'Heritage Temple Wellness & Royal Javanese Spa Weekend',
                'points_discounted' => 22000,
                'affinity_score' => 0.8200,
            ],
        ];

        // Store campaigns
        foreach ($packages as $pkg) {
            HtlPersonalizedTravelCampaign::create([
                'id' => (string) Str::uuid(),
                'travel_pass_id' => $pass->id,
                'campaign_code' => 'CAMP-'.strtoupper(Str::random(6)),
                'offer_title' => $pkg['package_title'],
                'destination_property_reference' => $pkg['destination'],
                'featured_package_title' => $pkg['package_title'],
                'points_required_discounted' => $pkg['points_discounted'],
                'valid_until' => now()->addDays(30),
                'affinity_score' => $pkg['affinity_score'],
                'interaction_status' => 'DELIVERED',
            ]);
        }

        return [
            'member_tier' => $pass->tier,
            'recommended_packages' => $packages,
        ];
    }
}
