<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ins_products')) {
            Schema::create('ins_products', function (Blueprint $table) {
                $table->id();
                $table->string('product_code')->unique(); // e.g. LGX_DELAY, VEH_CRASH, COLD_BREACH
                $table->string('name');
                $table->string('trigger_event_type'); // e.g. lgx.late, auto.collision, lgx.temp_breach
                $table->unsignedBigInteger('premium_amount_idr');
                $table->unsignedBigInteger('max_payout_idr');
                $table->boolean('is_embedded')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ins_policies')) {
            Schema::create('ins_policies', function (Blueprint $table) {
                $table->id();
                $table->string('policy_number')->unique();
                $table->foreignId('product_id')->constrained('ins_products');
                $table->foreignId('user_id')->constrained('users');
                $table->string('subject_ref_type')->nullable(); // shipment, vehicle, contract
                $table->string('subject_ref_id')->nullable();
                $table->unsignedBigInteger('premium_paid_idr');
                $table->unsignedBigInteger('coverage_limit_idr');
                $table->timestamp('starts_at');
                $table->timestamp('expires_at');
                $table->string('status')->default('active'); // active, claimed, lapsed
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ins_claims')) {
            Schema::create('ins_claims', function (Blueprint $table) {
                $table->id();
                $table->string('claim_number')->unique();
                $table->foreignId('policy_id')->constrained('ins_policies');
                $table->string('trigger_event_id')->index();
                $table->unsignedBigInteger('payout_amount_idr');
                $table->json('evidence_payload')->nullable();
                $table->string('status')->default('paid'); // pending_review, paid, rejected
                $table->timestamp('paid_at')->nullable();
                $table->string('idempotency_key')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_claims');
        Schema::dropIfExists('ins_policies');
        Schema::dropIfExists('ins_products');
    }
};
