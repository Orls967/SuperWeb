<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sct_sop_cycles')) {
            return;
        }

        Schema::table('sct_sop_cycles', function (Blueprint $table): void {
            if (! Schema::hasColumn('sct_sop_cycles', 'approval_id')) {
                $table->unsignedBigInteger('approval_id')->nullable()->after('signed_off_at');
                $table->index('approval_id', 'sct_sop_cycles_approval_idx');
            }
            if (! Schema::hasColumn('sct_sop_cycles', 'task_id')) {
                $table->string('task_id', 64)->nullable()->after('approval_id');
                $table->index('task_id', 'sct_sop_cycles_task_idx');
            }
            if (! Schema::hasColumn('sct_sop_cycles', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('task_id');
            }
            if (! Schema::hasColumn('sct_sop_cycles', 'published_by_user_id')) {
                $table->unsignedBigInteger('published_by_user_id')->nullable()->after('published_at');
                $table->index('published_by_user_id', 'sct_sop_cycles_pub_user_idx');
            }
            if (! Schema::hasColumn('sct_sop_cycles', 'consensus_snapshot_json')) {
                $table->json('consensus_snapshot_json')->nullable()->after('published_by_user_id');
            }
            if (! Schema::hasColumn('sct_sop_cycles', 'notes')) {
                $table->text('notes')->nullable()->after('consensus_snapshot_json');
            }
            if (! Schema::hasColumn('sct_sop_cycles', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('notes');
                $table->index('created_by_user_id', 'sct_sop_cycles_creator_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sct_sop_cycles')) {
            return;
        }

        Schema::table('sct_sop_cycles', function (Blueprint $table): void {
            $indexes = [
                'sct_sop_cycles_approval_idx' => 'approval_id',
                'sct_sop_cycles_task_idx' => 'task_id',
                'sct_sop_cycles_pub_user_idx' => 'published_by_user_id',
                'sct_sop_cycles_creator_idx' => 'created_by_user_id',
            ];

            foreach ($indexes as $indexName => $column) {
                if (Schema::hasColumn('sct_sop_cycles', $column)) {
                    $table->dropIndex($indexName);
                }
            }

            $columns = array_values(array_filter([
                'approval_id',
                'task_id',
                'published_at',
                'published_by_user_id',
                'consensus_snapshot_json',
                'notes',
                'created_by_user_id',
            ], static fn (string $col): bool => Schema::hasColumn('sct_sop_cycles', $col)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
