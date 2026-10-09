<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_transfer_pricing_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_code')->unique();
            $table->string('fiscal_year');
            $table->string('related_party_entity');
            $table->string('tp_method'); // TNMM, CUP, CostPlus, ResalePrice (438.1, 438.4)
            $table->decimal('arm_length_margin_percent', 5, 2);
            $table->string('comparability_confidence_label')->default('HIGH'); // 438.6
            $table->boolean('method_consistency_verified')->default(true); // 438.4
            $table->timestamps();
        });

        Schema::create('fin_tax_controversy_notices', function (Blueprint $table) {
            $table->id();
            $table->string('notice_code')->unique();
            $table->string('tax_authority_jurisdiction'); // e.g. DJP Indonesia, IRAS Singapore
            $table->decimal('disputed_tax_amount', 18, 2);
            $table->boolean('defense_pack_attached')->default(false); // 438.3, 438.5
            $table->decimal('contingent_provision_amount', 18, 2)->default(0.00); // 438.3, 438.5
            $table->string('status')->default('notice_received'); // notice_received, defense_filed, settled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_tax_controversy_notices');
        Schema::dropIfExists('fin_transfer_pricing_files');
    }
};
