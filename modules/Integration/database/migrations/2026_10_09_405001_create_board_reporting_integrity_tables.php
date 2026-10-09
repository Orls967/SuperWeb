<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_board_reporting_packs', function (Blueprint $table) {
            $table->id();
            $table->string('pack_code')->unique();
            $table->string('period'); // e.g. 2026-Q3
            $table->string('title');
            $table->decimal('reported_revenue', 18, 2);
            $table->decimal('ledger_verified_revenue', 18, 2);
            $table->text('narrative_summary');
            $table->string('preparer');
            $table->string('reviewer')->nullable();
            $table->string('approver')->nullable();
            $table->boolean('is_certified')->default(false); // 405.2
            $table->boolean('is_published')->default(false); // 405.4
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('gov_board_report_restatements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('gov_board_reporting_packs')->cascadeOnDelete();
            $table->integer('prior_version');
            $table->decimal('prior_revenue', 18, 2);
            $table->decimal('new_revenue', 18, 2);
            $table->text('reason_for_restatement');
            $table->string('approved_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_board_report_restatements');
        Schema::dropIfExists('gov_board_reporting_packs');
    }
};
