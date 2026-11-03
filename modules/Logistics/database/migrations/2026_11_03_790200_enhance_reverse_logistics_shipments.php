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
            $table->boolean('is_reverse')->default(false)->after('mode');
            $table->string('reverse_reason', 32)->nullable()->after('is_reverse'); // retur, oli_bekas, jelantah, scrap, material_sekunder
            $table->foreignId('source_party_id')->nullable()->after('reverse_reason')->constrained('users')->nullOnDelete();
            $table->string('material_code', 32)->nullable()->after('source_party_id');
            $table->string('grade_code', 16)->nullable()->after('material_code');
            $table->unsignedBigInteger('net_weight_grams')->nullable()->after('grade_code');
            $table->unsignedBigInteger('verified_weight_grams')->nullable()->after('net_weight_grams');
            $table->string('reverse_manifest_number', 64)->nullable()->after('verified_weight_grams');
            $table->string('reverse_chain_hash', 64)->nullable()->after('reverse_manifest_number');
            $table->string('plant_id', 64)->nullable()->after('reverse_chain_hash');
            $table->unsignedBigInteger('plant_receipt_id')->nullable()->after('plant_id');
            $table->timestamp('plant_received_at')->nullable()->after('plant_receipt_id');
            $table->unsignedBigInteger('carrier_cost_idr')->default(0)->after('plant_received_at');
            $table->string('supplier_party_id', 64)->nullable()->after('carrier_cost_idr');
            $table->foreignId('destination_facility_id')->nullable()->after('destination_location_id')->constrained('lgx_locations')->nullOnDelete();

            $table->index(['is_reverse', 'status']);
            $table->index(['reverse_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('lgx_shipments', function (Blueprint $table) {
            $table->dropForeign(['source_party_id']);
            $table->dropForeign(['destination_facility_id']);
            $table->dropIndex(['is_reverse', 'status']);
            $table->dropIndex(['reverse_reason']);
            $table->dropColumn([
                'is_reverse',
                'reverse_reason',
                'source_party_id',
                'material_code',
                'grade_code',
                'net_weight_grams',
                'verified_weight_grams',
                'reverse_manifest_number',
                'reverse_chain_hash',
                'plant_id',
                'plant_receipt_id',
                'plant_received_at',
                'carrier_cost_idr',
                'supplier_party_id',
                'destination_facility_id',
            ]);
        });
    }
};

