<?php

declare(strict_types=1);

use Modules\Hotel\Domain\Algorithms\BanquetBomExploder;
use Modules\Hotel\Domain\Algorithms\BanquetBomExplosionCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\BlockAllotmentReleaser;
use Modules\Hotel\Domain\Algorithms\BlockAllotmentReleaseCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\EventMarginCalculator;
use Modules\Hotel\Domain\Algorithms\EventMarginCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\MilestoneScheduleBuilder;
use Modules\Hotel\Domain\Algorithms\MilestoneScheduleCertificateVerifier;

test('H14 BlockAllotmentReleaser: protects quota when days > 30', function () {
    $releaser = new BlockAllotmentReleaser();
    $cert = $releaser->release(
        propertyId: 'HTL-BALI-01',
        blockId: 'BLK-MICE-001',
        initialRooms: 200,
        currentAllocatedRooms: 200,
        confirmedPickupRooms: 30,
        daysUntilEvent: 45
    );

    expect($cert->cutoffTier)->toBe(BlockAllotmentReleaser::TIER_PROTECTED)
        ->and($cert->roomsReleasedToPublic)->toBe(0)
        ->and($cert->remainingContractedRooms)->toBe(200);

    expect(BlockAllotmentReleaseCertificateVerifier::verify($cert))->toBeTrue();
});

test('H14 BlockAllotmentReleaser: releases 25% of unpicked at H-30 cutoff', function () {
    $releaser = new BlockAllotmentReleaser();
    $cert = $releaser->release(
        propertyId: 'HTL-BALI-01',
        blockId: 'BLK-MICE-001',
        initialRooms: 200,
        currentAllocatedRooms: 200,
        confirmedPickupRooms: 60,
        daysUntilEvent: 25
    );

    // unpicked = 200 - 60 = 140. 25% = 35. remaining = 165.
    expect($cert->cutoffTier)->toBe(BlockAllotmentReleaser::TIER_H30)
        ->and($cert->unpickedBefore)->toBe(140)
        ->and($cert->roomsReleasedToPublic)->toBe(35)
        ->and($cert->remainingContractedRooms)->toBe(165);

    expect(BlockAllotmentReleaseCertificateVerifier::verify($cert))->toBeTrue();
});

test('H14 BlockAllotmentReleaser: releases 50% at H-14 and 100% unpicked at H-7', function () {
    $releaser = new BlockAllotmentReleaser();

    // H-14 cutoff
    $certH14 = $releaser->release(
        propertyId: 'HTL-BALI-01',
        blockId: 'BLK-MICE-001',
        initialRooms: 200,
        currentAllocatedRooms: 165,
        confirmedPickupRooms: 85,
        daysUntilEvent: 10
    );
    // unpicked = 165 - 85 = 80. 50% = 40. remaining = 125.
    expect($certH14->cutoffTier)->toBe(BlockAllotmentReleaser::TIER_H14)
        ->and($certH14->roomsReleasedToPublic)->toBe(40)
        ->and($certH14->remainingContractedRooms)->toBe(125);
    expect(BlockAllotmentReleaseCertificateVerifier::verify($certH14))->toBeTrue();

    // Final H-7 cutoff
    $certH7 = $releaser->release(
        propertyId: 'HTL-BALI-01',
        blockId: 'BLK-MICE-001',
        initialRooms: 200,
        currentAllocatedRooms: 125,
        confirmedPickupRooms: 110,
        daysUntilEvent: 5
    );
    // unpicked = 125 - 110 = 15. 100% = 15. remaining = 110 (exactly equals confirmed pickups!).
    expect($certH7->cutoffTier)->toBe(BlockAllotmentReleaser::TIER_H7_FINAL)
        ->and($certH7->roomsReleasedToPublic)->toBe(15)
        ->and($certH7->remainingContractedRooms)->toBe(110)
        ->and($certH7->remainingContractedRooms)->toBe($certH7->confirmedPickupRooms);
    expect(BlockAllotmentReleaseCertificateVerifier::verify($certH7))->toBeTrue();
});

test('H15 BanquetBomExploder: calculates raw food, beverage shrinkage, and labor hours', function () {
    $exploder = new BanquetBomExploder();
    $recipe = [
        [
            'item_id' => 'ING-BEEF',
            'name' => 'Wagyu Beef Tenderloin',
            'type' => 'food',
            'portion_qty' => 200.0,
            'unit' => 'gram',
            'cost_per_unit_idr' => 300,
            'yield_loss_ratio' => 0.10, // 10% trimming loss
        ],
        [
            'item_id' => 'BEV-ORANGE-JUICE',
            'name' => 'Fresh Orange Juice 1L',
            'type' => 'beverage',
            'portion_qty' => 250.0, // 250 ml per glass
            'unit' => 'ml',
            'cost_per_unit_idr' => 35000,
            'pour_shrinkage_ratio' => 0.05, // 5% spill variance
            'bottle_volume_ml' => 1000,
        ],
    ];

    $cert = $exploder->explode(
        beoId: 'BEO-2026-089',
        guaranteedPax: 300,
        recipeComponents: $recipe,
        safetyBufferRatio: 1.05,
        eventDurationHours: 4.0,
        hourlyLaborRateIdr: 50000
    );

    expect($cert->preparedPax)->toBe(315)
        ->and($cert->waitersRequired)->toBe(15) // ceil(300 / 20) = 15
        ->and($cert->cooksRequired)->toBe(8)    // ceil(300 / 40) = 8
        ->and($cert->totalLaborCostIdr)->toBe((15 + 8) * 4 * 50000) // 23 * 4 * 50000 = 4.600.000
        ->and($cert->totalFoodCogsIdr)->toBe(21000000) // (315 * 200 / 0.90) * 300 = 70.000g * 300 = 21.000.000
        ->and($cert->totalBeverageCogsIdr)->toBe(83 * 35000); // ceil(315 * 1.05) = 331 servings * 250 = 82.750 ml => 83 bottles * 35.000 = 2.905.000

    expect(BanquetBomExplosionCertificateVerifier::verify($cert))->toBeTrue();
});

test('H16 EventMarginCalculator: approves profitable proposals and enforces 40% margin guardrail', function () {
    $calculator = new EventMarginCalculator();

    // Skenario 1: Margin 54.67% >= 40% -> Approved
    $revApproved = [
        'hall_rental' => 50000000,
        'fnb_banquet' => 75000000,
        'av_lighting' => 15000000,
        'room_commission' => 10000000,
    ];
    $costApproved = [
        'food_cogs' => 35000000,
        'bev_cogs' => 10000000,
        'labor_cost' => 8000000,
        'av_vendor' => 15000000,
    ];

    $certApproved = $calculator->calculate('PROP-2026-001', $revApproved, $costApproved, 40.0);
    expect($certApproved->isCompliant)->toBeTrue()
        ->and($certApproved->status)->toBe(EventMarginCalculator::STATUS_APPROVED)
        ->and($certApproved->grossMarginPercent)->toBe(54.67)
        ->and($certApproved->priceShortfallIdr)->toBe(0);
    expect(EventMarginCertificateVerifier::verify($certApproved))->toBeTrue();

    // Skenario 2: Margin 30% < 40% -> Rejected with calculated shortfall
    $revBreach = ['total_sales' => 100000000];
    $costBreach = ['total_cost' => 70000000];
    $certBreach = $calculator->calculate('PROP-2026-002', $revBreach, $costBreach, 40.0);

    expect($certBreach->isCompliant)->toBeFalse()
        ->and($certBreach->status)->toBe(EventMarginCalculator::STATUS_REJECTED)
        ->and($certBreach->grossMarginPercent)->toBe(30.0)
        ->and($certBreach->priceShortfallIdr)->toBe(16666667); // ceil(70M / 0.60) - 100M = 116.666.667 - 100.000.000 = 16.666.667
    expect(EventMarginCertificateVerifier::verify($certBreach))->toBeTrue();
});

test('H17 MilestoneScheduleBuilder: creates staged payment milestones with perfect amount conservation', function () {
    $builder = new MilestoneScheduleBuilder();
    $cert = $builder->build(
        contractId: 'WED-2026-071',
        totalContractAmountIdr: 250000000,
        signingDate: '2026-11-08',
        eventStartDate: '2027-06-15'
    );

    expect($cert->isConserved)->toBeTrue()
        ->and($cert->totalMilestonesSumIdr)->toBe(250000000)
        ->and(count($cert->milestones))->toBe(4)
        ->and($cert->milestones[0]['amount_idr'])->toBe(50000000) // 20%
        ->and($cert->milestones[1]['amount_idr'])->toBe(75000000) // 30%
        ->and($cert->milestones[2]['amount_idr'])->toBe(75000000) // 30%
        ->and($cert->milestones[3]['amount_idr'])->toBe(50000000); // 20%

    expect(MilestoneScheduleCertificateVerifier::verify($cert))->toBeTrue();
});
