<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Application\Actions\ManualAdjustmentAction;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Application\Actions\TransferAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Fleet\Application\Services\FleetLeasingService;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Hotel\Application\Services\HotelFolioAndPackageService;
use Modules\Hotel\Application\Services\HotelFranchiseAndBrandService;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Venue\Application\Services\VenueCreatorEconomyService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(LedgerAccountsSeeder::class);
    $this->seed(BankingSeeder::class);
});

test('top up action without explicit idempotency key is idempotent and produces single transaction', function (): void {
    $user = User::factory()->create();
    $action = app(TopUpAction::class);

    expect(fn () => $action->execute($user, 100_000))
        ->toThrow(InvalidArgumentException::class);

    $tx1 = $action->execute($user, 100_000, 'req-topup-test-1');
    $tx2 = $action->execute($user, 100_000, 'req-topup-test-1');

    expect($tx1->id)->toBe($tx2->id);

    $count = LedgerTransaction::where('reference_type', User::class)
        ->where('reference_id', $user->id)
        ->where('type', TransactionType::TOPUP->value)
        ->count();

    expect($count)->toBe(1);
    expect((int) $user->walletAccount('IDR')->fresh()->cached_balance)->toBe(100_000);
});

test('transfer action without explicit key is idempotent and produces single transaction', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    app(SetPinAction::class)->execute($sender, '123456');
    app(TopUpAction::class)->execute($sender, 200_000, 'seed_sender_wallet');

    $transfer = app(TransferAction::class);

    expect(fn () => $transfer->execute($sender, $recipient, 50_000, '123456', 'Bayar makan siang'))
        ->toThrow(InvalidArgumentException::class);

    $tx1 = $transfer->execute($sender, $recipient, 50_000, '123456', 'Bayar makan siang', 'req-transfer-test-1');
    $tx2 = $transfer->execute($sender, $recipient, 50_000, '123456', 'Bayar makan siang', 'req-transfer-test-1');

    expect($tx1->id)->toBe($tx2->id);

    $count = LedgerTransaction::where('type', TransactionType::TRANSFER->value)
        ->where('created_by', $sender->id)
        ->count();

    expect($count)->toBe(1);
    expect((int) $recipient->walletAccount('IDR')->fresh()->cached_balance)->toBe(50_000);
    expect((int) $sender->walletAccount('IDR')->fresh()->cached_balance)->toBe(150_000);
});

test('manual adjustment action is idempotent producing single ledger transaction', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $account = LedgerAccount::where('code', 'fee:banking:IDR')->firstOrFail();
    $initialBalance = (int) $account->cached_balance;

    $action = app(ManualAdjustmentAction::class);

    expect(fn () => $action->execute($account, 25_000, 'Koreksi audit penyesuaian', $admin))
        ->toThrow(InvalidArgumentException::class);

    $tx1 = $action->execute($account, 25_000, 'Koreksi audit penyesuaian', $admin, 'req-adj-test-1');
    $tx2 = $action->execute($account, 25_000, 'Koreksi audit penyesuaian', $admin, 'req-adj-test-1');

    expect($tx1->id)->toBe($tx2->id);

    $count = LedgerTransaction::where('type', TransactionType::MANUAL_ADJUSTMENT->value)
        ->where('reference_id', $account->id)
        ->count();

    expect($count)->toBe(1);
    expect((int) $account->fresh()->cached_balance)->toBe($initialBalance + 25_000);
});

test('fleet leasing monthly amortization is idempotent and avoids double accumulation', function (): void {
    $contract = FleetContract::create([
        'contract_number' => 'FLT-IDEMPOTENT-001',
        'party_id' => '501',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYears(3)->toDateString(),
        'monthly_rental_idr' => 15_000_000,
        'total_lease_value_idr' => 540_000_000,
        'accumulated_amortized_idr' => 0,
        'status' => 'active',
    ]);

    $service = app(FleetLeasingService::class);

    $service->amortizeMonthly($contract, 1);
    $service->amortizeMonthly($contract, 1);

    expect($contract->fresh()->accumulated_amortized_idr)->toBe(15_000_000);

    $count = LedgerTransaction::where('type', 'lease_amortization')
        ->where('reference_id', (string) $contract->id)
        ->count();

    expect($count)->toBe(1);
});

test('venue creator stream accrual is idempotent and does not double count held royalties', function (): void {
    $service = app(VenueCreatorEconomyService::class);
    $creator = $service->registerCreator('DJ ElectroNusantara', 'MELODIC_TECHNO');
    $contract = $service->createRightsContract($creator, 70.0, 14);

    $share1 = $service->accrueStreamRevenue($contract, 10_000_000);
    $share2 = $service->accrueStreamRevenue($contract, 10_000_000);

    expect($share1)->toBe(7_000_000);
    expect($share2)->toBe(7_000_000);

    $fresh = $contract->fresh();
    expect($fresh->accrued_royalties_idr)->toBe(7_000_000);
    expect($fresh->held_royalties_idr)->toBe(7_000_000);

    $count = LedgerTransaction::where('reference_type', 'RIGHTS_CONTRACT')
        ->where('reference_id', (string) $contract->id)
        ->count();

    expect($count)->toBe(1);
});

test('hotel destination package settlement and franchise royalty are idempotent', function (): void {
    $package = DestinationPackage::create([
        'id' => (string) Str::uuid(),
        'package_code' => 'PKG-BALI-IDEM-001',
        'title' => 'Bali Weekend Package',
        'total_price_idr' => 5_000_000,
        'vendor_shares' => [
            ['vendor' => 'HOTEL', 'amount' => 3_000_000, 'account' => 'vendor:hotel:IDR'],
            ['vendor' => 'FESTIVAL', 'amount' => 2_000_000, 'account' => 'vendor:festival:IDR'],
        ],
    ]);

    $hotelService = app(HotelFolioAndPackageService::class);
    $hotelService->settleDestinationPackage($package->id, 'cust-user-1');
    $hotelService->settleDestinationPackage($package->id, 'cust-user-1');

    $pkgCount = LedgerTransaction::where('type', 'HTL_DESTINATION_PKG_SETTLE')
        ->where('reference_id', $package->id)
        ->count();
    expect($pkgCount)->toBe(1);

    $property = HotelProperty::create([
        'id' => (string) Str::uuid(),
        'property_code' => 'HTL-BALI-FRAN-IDEM',
        'name' => 'Grand Horizon Luxury Resort & Spa',
        'property_type' => 'RESORT',
        'city' => 'Badung',
        'total_rooms' => 120,
    ]);

    $brandService = app(HotelFranchiseAndBrandService::class);
    $franchise = $brandService->onboardFranchise($property, 500_000_000, 5.0);

    $r1 = $brandService->billMonthlyRoyalty($franchise, 100_000_000, '2026-10');
    $r2 = $brandService->billMonthlyRoyalty($franchise, 100_000_000, '2026-10');

    expect($r1)->toBe(5_000_000);
    expect($r2)->toBe(5_000_000);

    $franCount = LedgerTransaction::where('type', 'HOTEL_FRANCHISE_ROYALTY_BILLING')
        ->where('reference_id', $franchise->id)
        ->count();
    expect($franCount)->toBe(1);
});
