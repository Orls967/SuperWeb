<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_hs_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('hs_code', 8)->unique();
            $table->string('description');
            $table->unsignedSmallInteger('bm_bp')->default(0); // bea masuk, basis poin (500 = 5%)
            $table->unsignedSmallInteger('ppn_bp')->default(1100);
            $table->unsignedSmallInteger('pph22_api_bp')->default(250); // importir ber-API
            $table->unsignedSmallInteger('pph22_non_api_bp')->default(750);
            $table->boolean('requires_inspection')->default(false); // lartas -> jalur merah
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lgx_customs_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('declaration_number', 32)->unique();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->string('type', 3); // PIB (impor), PEB (ekspor)
            $table->boolean('has_api')->default(false);
            $table->string('status', 16)->default('submitted'); // submitted, on_hold, cleared
            $table->string('lane', 8)->default('green'); // green, red
            $table->json('lines');
            $table->unsignedBigInteger('customs_value_idr');
            $table->unsignedBigInteger('bm_idr')->default(0);
            $table->unsignedBigInteger('ppn_idr')->default(0);
            $table->unsignedBigInteger('pph22_idr')->default(0);
            $table->unsignedBigInteger('total_duty_idr')->default(0);
            $table->string('hold_reason', 255)->nullable();
            $table->string('previous_shipment_status', 32)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'lane']);
            $table->index(['shipment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_customs_declarations');
        Schema::dropIfExists('lgx_hs_tariffs');
    }
};
