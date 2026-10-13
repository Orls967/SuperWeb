<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\MissingForeignKeyRule;

it('reports id columns without a foreign key, except registered cross-module and polymorphic references', function (): void {
    $code = <<<'PHP'
        <?php
        Schema::create('hsp_admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id');
            $table->unsignedBigInteger('ward_id')->index();
            $table->foreignId('patient_id')->constrained('hsp_patients');
            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('hsp_doctors');
            $table->unsignedBigInteger('insurer_party_id');
            $table->string('external_ref');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
        });
        PHP;

    $signatures = ruleSignatures(new MissingForeignKeyRule, 'modules/Hospital/database/migrations/2026_01_01_000000_create_admissions.php', $code);

    expect($signatures)->toBe(['hsp_admissions.bed_id', 'hsp_admissions.ward_id']);
});

it('checks columns added later with Schema::table and ignores non-migration files', function (): void {
    $code = <<<'PHP'
        <?php
        Schema::table('hsp_beds', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable();
        });
        PHP;

    expect(ruleSignatures(new MissingForeignKeyRule, 'modules/Hospital/database/migrations/2026_01_02_000000_add_ward.php', $code))->toBe(['hsp_beds.ward_id'])
        ->and(ruleSignatures(new MissingForeignKeyRule, 'modules/Hospital/Application/Services/X.php', $code))->toBe([]);
});
