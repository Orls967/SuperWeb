<?php

declare(strict_types=1);

namespace Modules\Med\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedProject;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedStudioBooking;
use Modules\Med\Domain\Models\MedTalentContract;
use RuntimeException;

class StudioAndContentProductionService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * 133.1 & 133.5 Studio facility creation & scheduling with conflict prevention.
     */
    public function createStudio(array $params): MedStudio
    {
        return MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => $params['studio_code'] ?? 'STU-'.strtoupper(Str::random(6)),
            'name' => $params['name'],
            'facility_type' => $params['facility_type'],
            'location_city' => $params['location_city'],
            'hourly_rate_minor' => (int) $params['hourly_rate_minor'],
            'full_day_rate_minor' => (int) $params['full_day_rate_minor'],
            'status' => 'AVAILABLE',
        ]);
    }

    public function bookStudio(array $params): MedStudioBooking
    {
        $studioId = $params['studio_id'];
        $startTime = Carbon::parse($params['start_time']);
        $endTime = Carbon::parse($params['end_time']);

        if ($endTime->lte($startTime)) {
            throw new RuntimeException('End time must be after start time.');
        }

        // Test (c) reject overlapping booking for same studio
        $overlap = MedStudioBooking::where('studio_id', $studioId)
            ->where('status', 'CONFIRMED')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            })
            ->exists();

        if ($overlap) {
            throw new RuntimeException("Studio booking clash detected for studio {$studioId}.");
        }

        return MedStudioBooking::create([
            'id' => (string) Str::uuid(),
            'booking_code' => 'BKG-'.strtoupper(Str::random(8)),
            'studio_id' => $studioId,
            'project_id' => $params['project_id'] ?? null,
            'client_entity_id' => $params['client_entity_id'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_price_minor' => (int) $params['total_price_minor'],
            'status' => 'CONFIRMED',
        ]);
    }

    /**
     * 133.2 Production lifecycle & cost aggregation.
     * Test (d): total project cost = crew + vendor + studio.
     */
    public function createProject(array $params): MedProject
    {
        return MedProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => $params['project_code'] ?? 'PRJ-'.strtoupper(Str::random(8)),
            'title' => $params['title'],
            'genre' => $params['genre'],
            'phase' => 'BRIEF',
            'budget_limit_minor' => (int) $params['budget_limit_minor'],
            'crew_cost_minor' => 0,
            'vendor_cost_minor' => 0,
            'studio_cost_minor' => 0,
            'total_production_cost_minor' => 0,
            'box_office_revenue_minor' => 0,
            'is_capitalized_as_asset' => false,
        ]);
    }

    public function recordProductionCosts(string $projectId, int $crewMinor, int $vendorMinor, int $studioMinor): MedProject
    {
        $project = MedProject::findOrFail($projectId);
        $project->crew_cost_minor += $crewMinor;
        $project->vendor_cost_minor += $vendorMinor;
        $project->studio_cost_minor += $studioMinor;

        $project->total_production_cost_minor = $project->crew_cost_minor + $project->vendor_cost_minor + $project->studio_cost_minor;
        $project->phase = 'POST';
        $project->save();

        return $project;
    }

    public function completeAndCapitalizeProject(string $projectId, bool $capitalizeAsAsset): MedProject
    {
        $project = MedProject::findOrFail($projectId);
        $project->phase = 'COMPLETED';
        $project->is_capitalized_as_asset = $capitalizeAsAsset;
        $project->save();

        return $project;
    }

    /**
     * 133.3 Talent backend contract & audited revenue royalty.
     * Test (a): backend % = revenue audited * rate.
     */
    public function registerTalentContract(array $params): MedTalentContract
    {
        return MedTalentContract::create([
            'id' => (string) Str::uuid(),
            'contract_code' => 'TCN-'.strtoupper(Str::random(8)),
            'project_id' => $params['project_id'],
            'talent_party_id' => $params['talent_party_id'],
            'role_name' => $params['role_name'],
            'upfront_fee_minor' => (int) $params['upfront_fee_minor'],
            'backend_percentage' => (float) $params['backend_percentage'],
            'calculated_royalty_minor' => 0,
            'payout_status' => 'HOLD',
        ]);
    }

    public function auditRevenueAndComputeRoyalty(string $projectId, int $auditedRevenueMinor): array
    {
        $project = MedProject::findOrFail($projectId);
        $project->box_office_revenue_minor = $auditedRevenueMinor;
        $project->save();

        $contracts = MedTalentContract::where('project_id', $projectId)->get();
        $totalRoyaltyMinor = 0;

        foreach ($contracts as $contract) {
            $royalty = (int) round(($auditedRevenueMinor * $contract->backend_percentage) / 100.0);
            $contract->calculated_royalty_minor = $royalty;
            $contract->payout_status = 'APPROVED';
            $contract->save();

            $totalRoyaltyMinor += $royalty;
        }

        // Ledger settlement for royalties
        if ($totalRoyaltyMinor > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: 'MEDIA_TALENT_ROYALTY_ACCRUAL',
                description: "Audited talent royalty accrual for project {$project->title}",
                idempotencyKey: 'MED-ROYALTY-'.$project->project_code.'-'.$auditedRevenueMinor,
                entries: [
                    PostingEntryDTO::forCode('med:talent_royalty_expense:IDR', 'IDR', $totalRoyaltyMinor),
                    PostingEntryDTO::forCode('med:talent_royalty_payable:IDR', 'IDR', -$totalRoyaltyMinor),
                ],
                referenceType: 'MEDIA_PROJECT',
                referenceId: $project->project_code,
            ));
        }

        return $contracts->toArray();
    }

    /**
     * 133.4 IP Asset & licensing.
     * Test (b): Reject overlapping IP license for identical territory and channel.
     */
    public function registerIpAsset(array $params): MedIpAsset
    {
        return MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-'.strtoupper(Str::random(8)),
            'title' => $params['title'],
            'ip_type' => $params['ip_type'],
            'owner_entity_id' => $params['owner_entity_id'],
            'status' => 'ACTIVE',
        ]);
    }

    public function issueIpLicense(array $params): MedIpLicense
    {
        $ipId = (string) $params['ip_id'];
        $channel = (string) $params['channel'];
        $territory = (string) $params['territory'];
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];

        if (strlen($ipId) > 64) {
            throw new \InvalidArgumentException('IP ID exceeds maximum length of 64 characters.');
        }
        if (strlen($channel) > 50) {
            throw new \InvalidArgumentException('Channel exceeds maximum length of 50 characters.');
        }
        if (strlen($territory) > 50) {
            throw new \InvalidArgumentException('Territory exceeds maximum length of 50 characters.');
        }

        // Overlapping territory & channel check
        $conflict = MedIpLicense::where('ip_id', $ipId)
            ->where('channel', $channel)
            ->where('territory', $territory)
            ->where('status', 'ACTIVE')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($conflict) {
            throw new RuntimeException("IP double-license conflict: {$channel} in {$territory} is already licensed.");
        }

        return MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-'.strtoupper(Str::random(8)),
            'ip_id' => $ipId,
            'licensee_entity_id' => $params['licensee_entity_id'],
            'channel' => $channel,
            'territory' => $territory,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'royalty_rate_pct' => (float) $params['royalty_rate_pct'],
            'minimum_guarantee_minor' => (int) ($params['minimum_guarantee_minor'] ?? 0),
            'status' => 'ACTIVE',
        ]);
    }
}
