<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\ExpirePointsAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Tenant;
use Tests\TestCase;

class ExpirePointsActionTest extends TestCase
{
    use RefreshDatabase;

    private User $memberUser;

    private ExpirePointsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->memberUser = User::firstOrCreate(
            ['email' => 'member.expire@duttamall.com'],
            ['name' => 'Member Expire', 'role' => 'customer', 'password' => bcrypt('password')]
        );

        $this->action = app(ExpirePointsAction::class);
    }

    public function test_returns_zero_when_no_expired_batches(): void
    {
        $expired = $this->action->execute(Carbon::parse('2020-01-01'));
        $this->assertSame(0, $expired);
    }

    public function test_expires_batches_and_posts_to_ledger(): void
    {
        $tenant = Tenant::firstOrFail();

        // Claim points to generate a batch
        $claimAction = app(ClaimReceiptPointsAction::class);
        $result = $claimAction->execute(
            user: $this->memberUser,
            receiptNumber: 'RCP-EXP-001',
            receiptAmount: 10000000,
            receiptDate: Carbon::parse('2025-01-01'),
            tenantId: $tenant->id,
        );

        $batch = PointBatch::where('user_id', $this->memberUser->id)->firstOrFail();
        $this->assertGreaterThan(0, $batch->points_remaining);
        $pointsToExpire = $batch->points_remaining;

        // Run expiration as of future date past expires_at
        $asOf = Carbon::parse($batch->expires_at)->addDays(1);
        $expiredTotal = $this->action->execute($asOf);

        $this->assertSame($pointsToExpire, $expiredTotal);

        $batch->refresh();
        $this->assertSame(0, $batch->points_remaining);
        $this->assertTrue($batch->is_expired);

        // Verify ledger transaction was posted with idempotency key
        $tx = LedgerTransaction::where('idempotency_key', 'mall:pts_expire:'.$batch->id)->firstOrFail();
        $this->assertSame(1, $tx->created_by);
        $meta = is_array($tx->meta) ? $tx->meta : json_decode($tx->meta, true);
        $this->assertSame($this->memberUser->id, $meta['user_id']);
        $this->assertSame($batch->id, $meta['batch_id']);
        $this->assertSame($pointsToExpire, $meta['expired_points']);

        // Idempotency: running again as of same date returns 0 and does not re-post
        $reExpired = $this->action->execute($asOf);
        $this->assertSame(0, $reExpired);
        $this->assertSame(1, LedgerTransaction::where('idempotency_key', 'mall:pts_expire:'.$batch->id)->count());
    }

    public function test_skips_batches_with_zero_points_or_missing_user(): void
    {
        $batchZero = PointBatch::create([
            'user_id' => $this->memberUser->id,
            'receipt_number' => 'RCP-ZERO',
            'points_earned' => 100,
            'points_remaining' => 0,
            'is_expired' => false,
            'earned_at' => Carbon::parse('2025-01-01'),
            'expires_at' => Carbon::parse('2025-02-01'),
        ]);

        $otherUser = User::create([
            'name' => 'To Be Deleted',
            'email' => 'todelete@duttamall.com',
            'password' => bcrypt('password'),
        ]);

        $batchMissingUser = PointBatch::create([
            'user_id' => $otherUser->id,
            'receipt_number' => 'RCP-NOUSER',
            'points_earned' => 100,
            'points_remaining' => 100,
            'is_expired' => false,
            'earned_at' => Carbon::parse('2025-01-01'),
            'expires_at' => Carbon::parse('2025-02-01'),
        ]);

        // Disable foreign key checks momentarily to delete user while keeping batch
        DB::statement('PRAGMA foreign_keys = OFF');
        $otherUser->delete();
        DB::statement('PRAGMA foreign_keys = ON');

        $expired = $this->action->execute(Carbon::parse('2025-03-01'));
        $this->assertSame(0, $expired);
    }
}
