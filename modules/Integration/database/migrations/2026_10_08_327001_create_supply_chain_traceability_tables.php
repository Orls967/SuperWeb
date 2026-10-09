<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traceable_supply_chain_origins', function (Blueprint $table) {
            $table->id();
            $table->string('provenance_batch_code')->unique();
            $table->string('supplier_id')->index();
            $table->string('commodity_type'); // MINERALS, TIMBER, SEAFOOD, TEXTILES, PHARMA
            $table->boolean('has_trace_gap')->default(false); // 327.4 Trace gap blocks verified claim
            $table->boolean('chain_of_custody_verified')->default(false);
            $table->boolean('certificate_expired')->default(false); // 327.4 Expiry blocks shipment
            $table->boolean('shipment_cleared')->default(false);
            $table->timestamps();
        });

        Schema::create('responsible_sourcing_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_id')->unique();
            $table->string('supplier_name');
            $table->boolean('refused_traceability')->default(false); // 327.5 Edge case
            $table->boolean('is_suspended')->default(false); // 327.2 & 327.4 Blocks PO
            $table->string('suspension_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsible_sourcing_suppliers');
        Schema::dropIfExists('traceable_supply_chain_origins');
    }
};
