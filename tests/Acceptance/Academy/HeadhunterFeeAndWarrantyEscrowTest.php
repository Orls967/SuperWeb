<?php

declare(strict_types=1);

namespace Tests\Acceptance\Academy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Edu\Application\Actions\SettleHeadhunterFeeAction;
use Modules\Edu\Domain\Exceptions\HeadhunterWarrantyNotExpiredException;
use Modules\Edu\Domain\Models\EduHeadhunterFee;
use Modules\Edu\Domain\Models\EduJobOpening;
use Modules\Edu\Domain\Models\EduPlacement;
use Modules\Edu\Ledger\EduAccounts;
use Modules\Ledger\Domain\Models\LedgerEntry;
use Tests\TestCase;

class HeadhunterFeeAndWarrantyEscrowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fee_calculation_is_exact_integer_rupiah_based_on_bps(): void
    {
        $annualSalary = 240_000_000; // IDR 240M
        $feeBps = 1500; // 15.00%
        $expectedFee = 36_000_000; // 240M * 0.15 = 36M

        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-CALC-01',
            'hiring_entity' => 'MegaCorp',
            'title' => 'Lead Architect',
            'capacity' => 1,
            'annual_salary_minor' => $annualSalary,
            'headhunter_fee_bps' => $feeBps,
            'required_credential_code' => 'PRG-ARCH-01',
            'status' => 'OPEN',
        ]);

        $user = User::factory()->create();
        $placement = EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-CALC-001',
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => Carbon::now()->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);

        $action = app(SettleHeadhunterFeeAction::class);
        $fee = $action->holdFee($placement);

        $this->assertEquals($expectedFee, $fee->fee_minor);
        $this->assertEquals('HELD', $fee->status);
    }

    public function test_placement_hold_creates_ledger_posting_to_escrow_account(): void
    {
        $user = User::factory()->create();
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-ESC-01',
            'hiring_entity' => 'EscrowCorp',
            'title' => 'DevOps Engineer',
            'capacity' => 1,
            'annual_salary_minor' => 100_000_000,
            'headhunter_fee_bps' => 1500, // 15M
            'required_credential_code' => 'PRG-DEVOPS-01',
            'status' => 'OPEN',
        ]);

        $placement = EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-ESC-001',
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => Carbon::now()->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);

        $action = app(SettleHeadhunterFeeAction::class);
        $fee = $action->holdFee($placement);

        // Verify ledger entries
        $escrowAccount = EduAccounts::ESCROW_HEADHUNTER_GUARANTEE;
        $receivableAccount = EduAccounts::HEADHUNTER_RECEIVABLE;

        $escrowEntries = LedgerEntry::where('account_code', $escrowAccount)->get();
        $receivableEntries = LedgerEntry::where('account_code', $receivableAccount)->get();

        $this->assertNotEmpty($escrowEntries);
        $this->assertNotEmpty($receivableEntries);

        $this->assertEquals(15_000_000, $escrowEntries->sum('amount')); // Credit (+)
        $this->assertEquals(-15_000_000, $receivableEntries->sum('amount')); // Debit (-)
    }

    public function test_capture_escrow_after_90_days_recognizes_headhunter_revenue(): void
    {
        $user = User::factory()->create();
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-CAP-01',
            'hiring_entity' => 'SuccessCorp',
            'title' => 'Senior Engineer',
            'capacity' => 1,
            'annual_salary_minor' => 120_000_000,
            'headhunter_fee_bps' => 1500, // 18M
            'required_credential_code' => 'PRG-SR-01',
            'status' => 'OPEN',
        ]);

        $placedDate = Carbon::now()->subDays(95);
        $placement = EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-CAP-001',
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => $placedDate->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);

        $action = app(SettleHeadhunterFeeAction::class);
        $fee = $action->holdFee($placement);

        // Capture after 95 days
        $action->captureFee($fee, Carbon::now());

        $fee->refresh();
        $this->assertEquals('CAPTURED', $fee->status);
        $this->assertNotNull($fee->captured_at);

        // Verify revenue recognition in ledger
        $revenueAccount = EduAccounts::HEADHUNTER_REVENUE;
        $escrowAccount = EduAccounts::ESCROW_HEADHUNTER_GUARANTEE;

        $revenueBalance = LedgerEntry::where('account_code', $revenueAccount)->sum('amount');
        $escrowBalance = LedgerEntry::where('account_code', $escrowAccount)->sum('amount');

        $this->assertEquals(18_000_000, $revenueBalance, 'Headhunter revenue recognized');
        $this->assertEquals(0, $escrowBalance, 'Escrow returned to 0 after capture');
    }

    public function test_cannot_capture_escrow_before_guarantee_period_ends(): void
    {
        $user = User::factory()->create();
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-EARLY-01',
            'hiring_entity' => 'EarlyCorp',
            'title' => 'Specialist',
            'capacity' => 1,
            'annual_salary_minor' => 100_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-SPEC-01',
            'status' => 'OPEN',
        ]);

        $placedDate = Carbon::now()->subDays(45); // Only 45 days, guarantee is 90
        $placement = EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-EARLY-001',
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => $placedDate->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);

        $action = app(SettleHeadhunterFeeAction::class);
        $fee = $action->holdFee($placement);

        $this->expectException(HeadhunterWarrantyNotExpiredException::class);
        $action->captureFee($fee, Carbon::now());
    }

    public function test_candidate_early_departure_releases_escrow_back_to_client(): void
    {
        $user = User::factory()->create();
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-REL-01',
            'hiring_entity' => 'RefundCorp',
            'title' => 'Junior Analyst',
            'capacity' => 1,
            'annual_salary_minor' => 80_000_000,
            'headhunter_fee_bps' => 1500, // 12M
            'required_credential_code' => 'PRG-JR-01',
            'status' => 'OPEN',
        ]);

        $placedDate = Carbon::now()->subDays(30);
        $placement = EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-REL-001',
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => $placedDate->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);

        $action = app(SettleHeadhunterFeeAction::class);
        $fee = $action->holdFee($placement);

        // Candidate resigns at day 30, fee is released / refunded
        $action->releaseFee($fee, 'Candidate resigned during probation period');

        $fee->refresh();
        $placement->refresh();
        $this->assertEquals('RELEASED', $fee->status);
        $this->assertEquals('RELEASED', $placement->status);
        $this->assertNotNull($fee->released_at);

        // Ledger: escrow reversed
        $escrowAccount = EduAccounts::ESCROW_HEADHUNTER_GUARANTEE;
        $receivableAccount = EduAccounts::HEADHUNTER_RECEIVABLE;

        $this->assertEquals(0, LedgerEntry::where('account_code', $escrowAccount)->sum('amount'));
        $this->assertEquals(0, LedgerEntry::where('account_code', $receivableAccount)->sum('amount'));
    }

    public function test_double_capture_or_release_is_rejected(): void
    {
        $user = User::factory()->create();
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-DBL-01',
            'hiring_entity' => 'DoubleCorp',
            'title' => 'Staff',
            'capacity' => 1,
            'annual_salary_minor' => 60_000_000,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-STF-01',
            'status' => 'OPEN',
        ]);

        $placement = EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-DBL-001',
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => Carbon::now()->subDays(100)->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);

        $action = app(SettleHeadhunterFeeAction::class);
        $fee = $action->holdFee($placement);
        $action->captureFee($fee, Carbon::now());

        $this->expectException(InvalidArgumentException::class);
        $action->captureFee($fee, Carbon::now());
    }

    public function test_escrow_account_balance_reconciles_exactly_with_held_fees(): void
    {
        $openings = [
            $this->createOpeningAndPlacement(100_000_000),
            $this->createOpeningAndPlacement(200_000_000),
            $this->createOpeningAndPlacement(150_000_000),
        ];

        $action = app(SettleHeadhunterFeeAction::class);
        $fees = [];
        foreach ($openings as $plc) {
            $fees[] = $action->holdFee($plc);
        }

        // Expected held fees: (100M + 200M + 150M) * 15% = 67.5M
        $totalHeld = EduHeadhunterFee::where('status', 'HELD')->sum('fee_minor');
        $this->assertEquals(67_500_000, $totalHeld);

        $escrowAccount = EduAccounts::ESCROW_HEADHUNTER_GUARANTEE;
        $escrowBalance = LedgerEntry::where('account_code', $escrowAccount)->sum('amount');
        $this->assertEquals($totalHeld, $escrowBalance);
    }

    private function createOpeningAndPlacement(int $salary): EduPlacement
    {
        $user = User::factory()->create();
        $opening = EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'opening_code' => 'JOB-'.strtoupper(Str::random(6)),
            'hiring_entity' => 'Corp',
            'title' => 'Role',
            'capacity' => 1,
            'annual_salary_minor' => $salary,
            'headhunter_fee_bps' => 1500,
            'required_credential_code' => 'PRG-GEN-01',
            'status' => 'OPEN',
        ]);

        return EduPlacement::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-'.strtoupper(Str::random(6)),
            'student_user_id' => $user->id,
            'opening_id' => $opening->id,
            'placed_at' => Carbon::now()->toDateString(),
            'guarantee_days' => 90,
            'status' => 'PLACED',
        ]);
    }
}
