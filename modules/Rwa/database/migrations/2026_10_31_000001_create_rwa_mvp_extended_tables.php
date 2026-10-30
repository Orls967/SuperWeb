<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rwa_assets') && ! Schema::hasColumn('rwa_assets', 'creator_id')) {
            Schema::table('rwa_assets', function (Blueprint $table) {
                $table->foreignId('creator_id')->nullable()->constrained('users');
            });
        }

        if (! Schema::hasTable('rwa_investor_verifications')) {
            Schema::create('rwa_investor_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kyc_document_id');
                $table->string('approval_id')->nullable();
                $table->string('status')->default('VERIFIED'); // VERIFIED, EXPIRED, REVOKED
                $table->timestamp('verified_at');
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->unique('user_id');
                $table->index(['user_id', 'status', 'expires_at']);
            });
        }

        if (! Schema::hasTable('rwa_offering_orders')) {
            Schema::create('rwa_offering_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offering_id')->constrained('rwa_offerings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('quantity_requested');
                $table->unsignedBigInteger('quantity_allocated')->default(0);
                $table->string('hold_reference_id')->nullable();
                $table->unsignedBigInteger('amount_held_idr');
                $table->unsignedBigInteger('amount_captured_idr')->default(0);
                $table->string('status')->default('HELD'); // HELD, ALLOCATED, SETTLED, CANCELLED
                $table->string('idempotency_key')->unique();
                $table->timestamps();

                $table->index(['offering_id', 'status']);
            });
        }

        if (! Schema::hasTable('rwa_token_transfers')) {
            Schema::create('rwa_token_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rwa_asset_id')->constrained('rwa_assets')->cascadeOnDelete();
                $table->foreignId('from_user_id')->nullable()->constrained('users');
                $table->foreignId('to_user_id')->constrained('users');
                $table->unsignedBigInteger('quantity');
                $table->unsignedBigInteger('price_per_token_idr')->default(0);
                $table->string('transfer_type'); // PRIMARY_ISSUANCE, SECONDARY_TRADE, ESCROW_RELEASE
                $table->string('reference_id')->nullable();
                $table->string('idempotency_key')->unique();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['rwa_asset_id', 'to_user_id']);
                $table->index(['rwa_asset_id', 'from_user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rwa_token_transfers');
        Schema::dropIfExists('rwa_offering_orders');
        Schema::dropIfExists('rwa_investor_verifications');
    }
};
