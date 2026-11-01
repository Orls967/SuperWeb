<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Med\Application\Services\IpRightsRegistryService;
use Modules\Med\Domain\Models\MedIpRightsShare;
use RuntimeException;
use Tests\TestCase;

class IpRightsRegistryAndShareTest extends TestCase
{
    use RefreshDatabase;

    protected IpRightsRegistryService $rightsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->rightsService = app(IpRightsRegistryService::class);
    }

    public function test_register_ip_asset_creates_active_record(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-001',
            'title' => 'Nusantara Beats',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        expect($ip->status)->toBe('ACTIVE')
            ->and($ip->ip_code)->toBe('IP-HIT-001');
    }

    public function test_set_rights_shares_where_sum_is_10000_bps_succeeds(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-002',
            'title' => 'Garuda Anthem',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $shares = [
            ['rightsholder_party_id' => 'PARTY-COMPOSER', 'share_bps' => 5000],
            ['rightsholder_party_id' => 'PARTY-LYRICIST', 'share_bps' => 3000],
            ['rightsholder_party_id' => 'PARTY-PRODUCER', 'share_bps' => 2000],
        ];

        $result = $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => $shares,
        ]);

        expect(count($result))->toBe(3)
            ->and(MedIpRightsShare::where('ip_id', $ip->id)->where('rights_type', 'MASTER')->sum('share_bps'))->toBe(10000);
    }

    public function test_set_rights_shares_where_sum_is_not_10000_bps_is_rejected(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-003',
            'title' => 'Invalid Share Song',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        // Total = 9999 bps
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-A', 'share_bps' => 5000],
                ['rightsholder_party_id' => 'PARTY-B', 'share_bps' => 4999],
            ],
        ]);
    }

    public function test_rights_share_update_is_append_only_with_effective_date(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-004',
            'title' => 'Evolving Rights Song',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        // Initial split from 2026-01-01: A=50%, B=50%
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-A', 'share_bps' => 5000],
                ['rightsholder_party_id' => 'PARTY-B', 'share_bps' => 5000],
            ],
        ]);

        // Sold part to C from 2026-07-01: A=40%, B=40%, C=20%
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-07-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-A', 'share_bps' => 4000],
                ['rightsholder_party_id' => 'PARTY-B', 'share_bps' => 4000],
                ['rightsholder_party_id' => 'PARTY-C', 'share_bps' => 2000],
            ],
        ]);

        expect(MedIpRightsShare::where('ip_id', $ip->id)->count())->toBe(5);
    }

    public function test_get_active_shares_at_returns_correct_split_for_date(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-005',
            'title' => 'Historical Split IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-OLD-1', 'share_bps' => 6000],
                ['rightsholder_party_id' => 'PARTY-OLD-2', 'share_bps' => 4000],
            ],
        ]);

        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-06-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-NEW-1', 'share_bps' => 7000],
                ['rightsholder_party_id' => 'PARTY-NEW-2', 'share_bps' => 3000],
            ],
        ]);

        // Prior to June: returns 60/40 split
        $marchShares = $this->rightsService->getActiveSharesAt($ip->id, 'MASTER', CarbonImmutable::parse('2026-03-15'));
        expect($marchShares['PARTY-OLD-1'])->toBe(6000)
            ->and($marchShares['PARTY-OLD-2'])->toBe(4000);

        // After June: returns 70/30 split
        $julyShares = $this->rightsService->getActiveSharesAt($ip->id, 'MASTER', CarbonImmutable::parse('2026-07-01'));
        expect($julyShares['PARTY-NEW-1'])->toBe(7000)
            ->and($julyShares['PARTY-NEW-2'])->toBe(3000);
    }

    public function test_multiple_rights_types_have_independent_10000_bps_pools(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-006',
            'title' => 'Multi Rights IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        // Master rights: 100% to Record Label
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-RECORD-LABEL', 'share_bps' => 10000],
            ],
        ]);

        // Publishing rights: 50% Composer, 50% Publisher
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'PUBLISHING',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-COMPOSER', 'share_bps' => 5000],
                ['rightsholder_party_id' => 'PARTY-PUBLISHER', 'share_bps' => 5000],
            ],
        ]);

        $masterSum = MedIpRightsShare::where('ip_id', $ip->id)->where('rights_type', 'MASTER')->sum('share_bps');
        $pubSum = MedIpRightsShare::where('ip_id', $ip->id)->where('rights_type', 'PUBLISHING')->sum('share_bps');

        expect($masterSum)->toBe(10000)
            ->and($pubSum)->toBe(10000);
    }

    public function test_rights_share_records_cannot_be_deleted(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-007',
            'title' => 'Immutable Share IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $shares = $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-IMMUTABLE', 'share_bps' => 10000],
            ],
        ]);

        $shareModel = $shares[0];
        $this->expectException(RuntimeException::class);
        $shareModel->delete();
    }

    public function test_duplicate_effective_date_replaces_or_rejects_ambiguity(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-008',
            'title' => 'Duplicate Date IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-FIRST', 'share_bps' => 10000],
            ],
        ]);

        // Attempting to set different shares for identical effective date is rejected
        $this->expectException(InvalidArgumentException::class);
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-SECOND', 'share_bps' => 10000],
            ],
        ]);
    }

    public function test_negative_or_zero_share_bps_is_rejected(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-009',
            'title' => 'Negative Share IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-01-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-POS', 'share_bps' => 11000],
                ['rightsholder_party_id' => 'PARTY-NEG', 'share_bps' => -1000],
            ],
        ]);
    }

    public function test_query_active_shares_before_genesis_effective_date_returns_empty(): void
    {
        $ip = $this->rightsService->registerAsset([
            'ip_code' => 'IP-HIT-010',
            'title' => 'Pre-Genesis IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-LABEL-01',
        ]);

        $this->rightsService->setRightsShares([
            'ip_id' => $ip->id,
            'rights_type' => 'MASTER',
            'effective_date' => '2026-06-01',
            'shares' => [
                ['rightsholder_party_id' => 'PARTY-FUTURE', 'share_bps' => 10000],
            ],
        ]);

        $preShares = $this->rightsService->getActiveSharesAt($ip->id, 'MASTER', CarbonImmutable::parse('2025-12-31'));
        expect($preShares)->toBeEmpty();
    }
}
