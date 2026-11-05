<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_halal_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 32)->unique();
            $table->string('name', 128);
            $table->string('line_code', 16);
            $table->boolean('is_halal_mandatory')->default(true);
            $table->boolean('is_strictly_prohibited')->default(false);
            $table->string('risk_level', 16)->default('MEDIUM');
            $table->timestamps();

            $table->index(['line_code', 'is_strictly_prohibited']);
        });

        Schema::create('syb_halal_merchants', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_code', 64)->unique();
            $table->string('name', 128);
            $table->string('category_code', 32);
            $table->string('line_code', 16);
            $table->string('certificate_number', 64)->nullable();
            $table->string('issuing_authority', 64)->default('BPJPH');
            $table->date('valid_from')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->boolean('is_blacklisted')->default(false);
            $table->string('blacklist_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['category_code', 'line_code']);
            $table->index(['status', 'is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_halal_merchants');
        Schema::dropIfExists('syb_halal_categories');
    }
};
