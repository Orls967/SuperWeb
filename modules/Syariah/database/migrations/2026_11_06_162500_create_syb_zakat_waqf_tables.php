<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_zakat_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_user_id')->constrained('users');
            $table->unsignedSmallInteger('haul_year');
            $table->unsignedBigInteger('gold_price_per_gram_idr');
            $table->unsignedBigInteger('nisab_idr');
            $table->unsignedBigInteger('qualifying_wealth_idr');
            $table->unsignedBigInteger('due_debts_idr')->default(0);
            $table->unsignedBigInteger('net_zakat_base_idr');
            $table->unsignedSmallInteger('holding_days');
            $table->boolean('haul_fulfilled')->default(true);
            $table->boolean('reaches_nisab')->default(true);
            $table->boolean('is_obligated')->default(true);
            $table->unsignedBigInteger('zakat_amount_idr')->default(0);
            $table->boolean('is_collected')->default(false);
            $table->timestamp('collected_at')->nullable();
            $table->boolean('auto_debit')->default(false);
            $table->string('certificate_number', 64)->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->unique(['customer_user_id', 'haul_year'], 'uq_syb_zakat_cust_haul');
        });

        Schema::create('syb_zakat_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('haul_year');
            $table->string('asnaf', 32);
            $table->unsignedBigInteger('allocated_amount_idr');
            $table->unsignedBigInteger('distributed_amount_idr')->default(0);
            $table->unsignedBigInteger('mustahik_party_ref')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();
        });

        Schema::create('syb_waqf_principals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wakif_user_id')->constrained('users');
            $table->string('waqf_type', 16)->default('MONEY');
            $table->unsignedBigInteger('principal_amount_idr');
            $table->string('asset_description', 191)->nullable();
            $table->string('purpose', 191);
            $table->boolean('is_perpetual')->default(true);
            $table->string('certificate_number', 64)->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->index(['wakif_user_id', 'status'], 'idx_syb_waqf_wakif_status');
        });

        Schema::create('syb_waqf_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waqf_principal_id')->constrained('syb_waqf_principals')->cascadeOnDelete();
            $table->string('recipient_party_name', 191);
            $table->unsignedBigInteger('benefit_amount_idr');
            $table->date('distribution_date');
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();
        });

        Schema::create('syb_gapless_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number', 64)->unique();
            $table->string('certificate_type', 32);
            $table->foreignId('recipient_user_id')->constrained('users');
            $table->unsignedBigInteger('amount_idr');
            $table->string('hash_signature', 64);
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('syb_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('reason', 191)->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'idx_syb_trans_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_status_transitions');
        Schema::dropIfExists('syb_gapless_certificates');
        Schema::dropIfExists('syb_waqf_distributions');
        Schema::dropIfExists('syb_waqf_principals');
        Schema::dropIfExists('syb_zakat_distributions');
        Schema::dropIfExists('syb_zakat_assessments');
    }
};
