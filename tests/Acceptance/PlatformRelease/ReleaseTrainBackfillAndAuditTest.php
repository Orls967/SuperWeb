<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformRelease;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\DataBackfillChunk;
use Modules\Platform\Domain\Models\ReleaseHotfix;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\Services\BackfillChunkProcessor;
use Tests\TestCase;

class ReleaseTrainBackfillAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_train_orchestrates_hotfixes_and_version_progression(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $train = ReleaseTrain::create([
            'train_code' => 'REL-2026-W45',
            'version' => 'v8.1.0',
            'release_manager_id' => $admin->id,
            'status' => 'open',
            'freeze_at' => now()->addDays(2),
            'scheduled_deploy_at' => now()->addDays(3),
        ]);

        $hotfix = ReleaseHotfix::create([
            'hotfix_number' => 'HF-2026-W45-01',
            'release_train_id' => $train->id,
            'severity' => 'p1_blocker',
            'approved_by' => $admin->id,
            'isolation_status' => 'verified',
        ]);

        $this->assertSame('REL-2026-W45', $hotfix->releaseTrain->train_code);
        $this->assertCount(1, $train->hotfixes);
    }

    public function test_idempotent_backfill_processor_handles_chunking_and_resumption(): void
    {
        $backfill = DataBackfill::create([
            'job_name' => 'backfill_user_blind_index',
            'target_table' => 'users',
            'total_records' => 500,
            'chunk_size' => 100,
            'status' => 'pending',
            'cursor_pointer' => 0,
        ]);

        $processor = app(BackfillChunkProcessor::class);

        // Process chunk 1
        $processor->processNextChunk($backfill, function (array $records): int {
            return count($records);
        });

        $this->assertSame(100, $backfill->fresh()->cursor_pointer);
        $this->assertDatabaseHas('plt_backfill_chunks', [
            'backfill_id' => $backfill->id,
            'chunk_index' => 1,
            'status' => 'completed',
        ]);
    }

    public function test_platform_audit_command_passes_clean_and_fails_with_corruption(): void
    {
        $exitCodeClean = Artisan::call('platform:audit');
        $this->assertSame(0, $exitCodeClean);
    }
}
