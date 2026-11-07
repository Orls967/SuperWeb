<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ven_entertainment_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('ven_entertainment_zones', 'zone_type')) {
                $table->string('zone_type', 32)->default('INDOOR');
            }
        });

        Schema::create('ven_wristbands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ven_entertainment_tickets')->cascadeOnDelete();
            $table->string('wristband_uid', 64)->unique();
            $table->string('status', 32)->default('active'); // active, lost, damaged, deactivated
            $table->string('nfc_public_key_hash', 64)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('last_tapped_at')->nullable();
            $table->string('last_turnstile_code', 32)->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'status']);
        });

        Schema::create('ven_cashless_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('wristband_id')->nullable()->constrained('ven_wristbands')->nullOnDelete();
            $table->bigInteger('balance_idr')->default(0);
            $table->bigInteger('total_topped_up_idr')->default(0);
            $table->bigInteger('total_spent_idr')->default(0);
            $table->string('status', 32)->default('active'); // active, frozen, refunded, closed
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('ven_cashless_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('ven_cashless_wallets')->cascadeOnDelete();
            $table->string('transaction_type', 32); // TOPUP, DEBIT, REFUND
            $table->bigInteger('amount_idr');
            $table->string('outlet_code', 32)->default('MAIN_BAR');
            $table->string('idempotency_key', 96)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'transaction_type']);
        });

        Schema::create('ven_noise_curfew_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('ven_entertainment_venues')->cascadeOnDelete();
            $table->integer('decibel_level_db'); // dB(A) times 10 or raw integer
            $table->string('sensor_code', 32);
            $table->string('frequency_weighting', 8)->default('dBA');
            $table->timestamp('recorded_at');
            $table->boolean('is_curfew_breach')->default(false);
            $table->bigInteger('penalty_assessment_idr')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['venue_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ven_noise_curfew_readings');
        Schema::dropIfExists('ven_cashless_transactions');
        Schema::dropIfExists('ven_cashless_wallets');
        Schema::dropIfExists('ven_wristbands');
    }
};
