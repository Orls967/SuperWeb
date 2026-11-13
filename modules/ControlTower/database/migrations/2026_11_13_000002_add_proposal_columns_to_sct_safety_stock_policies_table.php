<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sct_safety_stock_policies')) {
            return;
        }

        Schema::table('sct_safety_stock_policies', function (Blueprint $table): void {
            if (! Schema::hasColumn('sct_safety_stock_policies', 'status')) {
                $table->string('status', 24)->default('approved')->after('dead_stock_flag')->index();
                $table->string('proposed_abc_class', 1)->nullable()->after('status');
                $table->string('proposed_xyz_class', 1)->nullable()->after('proposed_abc_class');
                $table->unsignedInteger('proposed_service_level_bps')->nullable()->after('proposed_xyz_class');
                $table->unsignedBigInteger('proposed_safety_stock_qty')->nullable()->after('proposed_service_level_bps');
                $table->unsignedBigInteger('proposed_reorder_point_qty')->nullable()->after('proposed_safety_stock_qty');
                $table->json('echelon_breakdown')->nullable()->after('proposed_reorder_point_qty');
                $table->json('proposed_echelon_breakdown')->nullable()->after('echelon_breakdown');
                $table->string('supplier_ref', 64)->nullable()->after('proposed_echelon_breakdown');
                $table->unsignedInteger('lead_time_mean_days')->nullable()->after('supplier_ref');
                $table->unsignedInteger('lead_time_sigma_days')->nullable()->after('lead_time_mean_days');
                $table->string('certificate_digest', 64)->nullable()->after('lead_time_sigma_days');
                $table->foreignId('approval_id')->nullable()->after('certificate_digest')->constrained('core_approvals')->nullOnDelete();
                $table->foreignId('proposed_by_user_id')->nullable()->after('approval_id')->constrained('users')->nullOnDelete();
                $table->foreignId('decided_by_user_id')->nullable()->after('proposed_by_user_id')->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable()->after('decided_by_user_id');
                $table->string('decision_notes', 255)->nullable()->after('decided_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sct_safety_stock_policies') || ! Schema::hasColumn('sct_safety_stock_policies', 'status')) {
            return;
        }

        Schema::table('sct_safety_stock_policies', function (Blueprint $table): void {
            $table->dropForeign(['approval_id']);
            $table->dropForeign(['proposed_by_user_id']);
            $table->dropForeign(['decided_by_user_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'proposed_abc_class',
                'proposed_xyz_class',
                'proposed_service_level_bps',
                'proposed_safety_stock_qty',
                'proposed_reorder_point_qty',
                'echelon_breakdown',
                'proposed_echelon_breakdown',
                'supplier_ref',
                'lead_time_mean_days',
                'lead_time_sigma_days',
                'certificate_digest',
                'approval_id',
                'proposed_by_user_id',
                'decided_by_user_id',
                'decided_at',
                'decision_notes',
            ]);
        });
    }
};
