<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->string('delivery_otp_hash')->nullable()->after('cancellation_fee_idr');
            $table->unsignedTinyInteger('failed_delivery_attempts')->default(0)->after('delivery_otp_hash');
        });

        Schema::create('lgx_proofs_of_delivery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained('lgx_shipments')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('lgx_drivers')->cascadeOnDelete();
            $table->string('receiver_name');
            $table->boolean('otp_verified')->default(true);
            $table->string('photo_path');
            $table->string('signature_path');
            $table->timestamp('delivered_at');
            $table->timestamps();
        });

        Schema::create('lgx_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('lgx_drivers')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('outcome', 16); // failed
            $table->string('reason_code', 32);
            $table->string('notes', 500)->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->unique(['shipment_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_delivery_attempts');
        Schema::dropIfExists('lgx_proofs_of_delivery');
        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->dropColumn(['delivery_otp_hash', 'failed_delivery_attempts']);
        });
    }
};
