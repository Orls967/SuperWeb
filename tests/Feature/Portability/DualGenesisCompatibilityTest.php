<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetEvent;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractVersion;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Domain\Models\Formula;
use Modules\Manufacturing\Domain\Models\Material;

uses(RefreshDatabase::class);

test('asset module accepts both new and legacy genesis but rejects random genesis', function () {
    $this->seed();
    $asset = Asset::firstOrFail();

    // Pastikan event pertama ada atau buat baru
    $event = AssetEvent::where('asset_id', $asset->id)->where('sequence', 1)->first();
    $nowIso = now()->toIso8601String();
    $payloadJson = json_encode(['action' => 'initial'], JSON_UNESCAPED_SLASHES);

    if (! $event) {
        $hashNew = AssetEvent::calculateHash(AssetService::GENESIS_HASH, 1, 'acquisition', $payloadJson, $nowIso);
        $eventId = (string) Str::uuid();
        DB::table('ast_events')->insert([
            'id' => $eventId,
            'asset_id' => $asset->id,
            'sequence' => 1,
            'event_type' => 'acquisition',
            'payload' => json_encode(['action' => 'initial']),
            'prev_hash' => AssetService::GENESIS_HASH,
            'hash' => $hashNew,
            'occurred_at' => $nowIso,
            'created_at' => $nowIso,
            'updated_at' => $nowIso,
        ]);
        $event = AssetEvent::find($eventId);
    }

    // 1. Genesis baru -> Lulus
    $hashNew = AssetEvent::calculateHash(AssetService::GENESIS_HASH, 1, (string) $event->event_type->value, $payloadJson, $nowIso);
    DB::table('ast_events')->where('id', $event->id)->update([
        'prev_hash' => AssetService::GENESIS_HASH,
        'hash' => $hashNew,
        'payload' => json_encode(['action' => 'initial']),
        'occurred_at' => $nowIso,
    ]);
    // Hapus event lanjutan bila ada agar chain sequence 1 valid
    DB::table('ast_events')->where('asset_id', $asset->id)->where('sequence', '>', 1)->delete();

    $exitCodeNew = Artisan::call('ast:verify-chain', ['--asset' => $asset->id]);
    expect($exitCodeNew)->toBe(0, 'Asset audit must pass with new genesis');

    // 2. Genesis lama (LEGACY_GENESIS_HASH) -> Lulus
    $legacyGenesis = defined(AssetService::class.'::LEGACY_GENESIS_HASH')
        ? AssetService::LEGACY_GENESIS_HASH
        : 'GENESIS_AST_0000000000000000000000000000000000000000000000000000000000000';
    $hashLegacy = AssetEvent::calculateHash($legacyGenesis, 1, (string) $event->event_type->value, $payloadJson, $nowIso);
    DB::table('ast_events')->where('id', $event->id)->update([
        'prev_hash' => $legacyGenesis,
        'hash' => $hashLegacy,
    ]);

    $exitCodeLegacy = Artisan::call('ast:verify-chain', ['--asset' => $asset->id]);
    expect($exitCodeLegacy)->toBe(0, 'Asset audit must pass with legacy genesis');

    // 3. Genesis acak -> Gagal (exit != 0)
    $randomGenesis = 'GENESIS_AST_RANDOM_CORRUPTED_HASH_VALUE_1234567890';
    $hashRandom = AssetEvent::calculateHash($randomGenesis, 1, (string) $event->event_type->value, $payloadJson, $nowIso);
    DB::table('ast_events')->where('id', $event->id)->update([
        'prev_hash' => $randomGenesis,
        'hash' => $hashRandom,
    ]);

    $exitCodeRandom = Artisan::call('ast:verify-chain', ['--asset' => $asset->id]);
    expect($exitCodeRandom)->not->toBe(0, 'Asset audit must fail with random corrupted genesis');
});

test('contract module accepts both new and legacy genesis but rejects random genesis', function () {
    $this->seed();
    $contract = Contract::firstOrFail();

    $version = ContractVersion::where('contract_id', $contract->id)->where('sequence', 1)->first();
    $nowIso = now()->toIso8601String();
    $body = 'Test contract body';

    if (! $version) {
        $hashNew = ContractVersion::calculateHash(
            prevHash: ContractVersion::GENESIS_HASH,
            sequence: 1,
            changeType: 'creation',
            body: $body,
            createdAtIso: $nowIso
        );
        $versionId = (string) Str::uuid();
        DB::table('ctr_contract_versions')->insert([
            'id' => $versionId,
            'contract_id' => $contract->id,
            'sequence' => 1,
            'change_type' => 'creation',
            'body' => $body,
            'metadata' => json_encode(['initial' => true]),
            'prev_hash' => ContractVersion::GENESIS_HASH,
            'hash' => $hashNew,
            'created_by_name' => 'Tester',
            'created_at' => $nowIso,
        ]);
        $version = ContractVersion::find($versionId);
    }

    // Hapus versi lanjutan agar sequence 1 terisolasi
    DB::table('ctr_contract_versions')->where('contract_id', $contract->id)->where('sequence', '>', 1)->delete();

    // 1. Genesis baru -> Lulus
    $hashNew = ContractVersion::calculateHash(
        prevHash: ContractVersion::GENESIS_HASH,
        sequence: 1,
        changeType: (string) $version->change_type,
        body: $body,
        createdAtIso: $nowIso
    );
    DB::table('ctr_contract_versions')->where('id', $version->id)->update([
        'body' => $body,
        'prev_hash' => ContractVersion::GENESIS_HASH,
        'hash' => $hashNew,
        'created_at' => $nowIso,
    ]);

    $exitCodeNew = Artisan::call('contracts:verify-chain', ['--contract' => $contract->id]);
    expect($exitCodeNew)->toBe(0, 'Contract audit must pass with new genesis');

    // 2. Genesis lama (LEGACY_GENESIS_HASH) -> Lulus
    $legacyGenesis = defined(ContractVersion::class.'::LEGACY_GENESIS_HASH')
        ? ContractVersion::LEGACY_GENESIS_HASH
        : 'GENESIS_CTR_000000000000000000000000000000000000000000000000000000000000';
    $hashLegacy = ContractVersion::calculateHash(
        prevHash: $legacyGenesis,
        sequence: 1,
        changeType: (string) $version->change_type,
        body: $body,
        createdAtIso: $nowIso
    );
    DB::table('ctr_contract_versions')->where('id', $version->id)->update([
        'prev_hash' => $legacyGenesis,
        'hash' => $hashLegacy,
    ]);

    $exitCodeLegacy = Artisan::call('contracts:verify-chain', ['--contract' => $contract->id]);
    expect($exitCodeLegacy)->toBe(0, 'Contract audit must pass with legacy genesis');

    // 3. Genesis acak -> Gagal (exit != 0)
    $randomGenesis = 'GENESIS_CTR_RANDOM_CORRUPTED_HASH_VALUE_1234567890';
    $hashRandom = ContractVersion::calculateHash(
        prevHash: $randomGenesis,
        sequence: 1,
        changeType: (string) $version->change_type,
        body: $body,
        createdAtIso: $nowIso
    );
    DB::table('ctr_contract_versions')->where('id', $version->id)->update([
        'prev_hash' => $randomGenesis,
        'hash' => $hashRandom,
    ]);

    $exitCodeRandom = Artisan::call('contracts:verify-chain', ['--contract' => $contract->id]);
    expect($exitCodeRandom)->not->toBe(0, 'Contract audit must fail with random corrupted genesis');
});

test('manufacturing module accepts both new and legacy genesis but rejects random genesis', function () {
    $this->seed();
    $material = Material::first() ?? Material::create([
        'code' => 'MAT-GEN-001',
        'name' => 'Genesis Test Material',
        'type' => 'raw',
        'uom' => 'kg',
    ]);
    $service = app(ManufacturingService::class);
    $body = ['yield' => 100];

    // Bersihkan formula lama untuk material ini
    Formula::where('output_material_id', $material->id)->delete();

    // 1. Genesis baru -> Lulus
    $hashNew = $service->calculateFormulaHash(ManufacturingService::GENESIS, 1, $body);
    $formula = Formula::create([
        'output_material_id' => $material->id,
        'version' => 1,
        'name' => 'Formula Genesis Test',
        'standard_yield_percent' => 100,
        'yield_tolerance_percent' => 5,
        'active_ingredients' => $body,
        'effective_from' => '2026-10-01',
        'status' => 'approved',
        'prev_hash' => ManufacturingService::GENESIS,
        'hash' => $hashNew,
    ]);

    expect($service->verifyFormulaChain($material))->toBeTrue('Manufacturing formula chain must pass with new genesis');

    // 2. Genesis lama (LEGACY_GENESIS) -> Lulus
    $legacyGenesis = defined(ManufacturingService::class.'::LEGACY_GENESIS')
        ? ManufacturingService::LEGACY_GENESIS
        : 'GENESIS_MFG_000000000000000000000000000000000000000000000000000000000000';
    $hashLegacy = $service->calculateFormulaHash($legacyGenesis, 1, $body);
    $formula->update([
        'prev_hash' => $legacyGenesis,
        'hash' => $hashLegacy,
    ]);

    expect($service->verifyFormulaChain($material))->toBeTrue('Manufacturing formula chain must pass with legacy genesis');

    // 3. Genesis acak -> Gagal
    $randomGenesis = 'GENESIS_MFG_RANDOM_CORRUPTED_HASH_VALUE_1234567890';
    $hashRandom = $service->calculateFormulaHash($randomGenesis, 1, $body);
    $formula->update([
        'prev_hash' => $randomGenesis,
        'hash' => $hashRandom,
    ]);

    expect($service->verifyFormulaChain($material))->toBeFalse('Manufacturing formula chain must fail with random genesis');
});
