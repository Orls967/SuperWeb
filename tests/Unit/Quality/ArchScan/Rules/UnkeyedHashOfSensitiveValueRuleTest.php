<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\UnkeyedHashOfSensitiveValueRule;

it('reports encodings and unkeyed hashes written to sensitive columns', function (): void {
    $code = <<<'PHP'
        <?php
        Employee::create(['name' => $name, 'nik_hash' => hash('sha256', $nik)]);
        $formula->forceFill(['formula_payload_encrypted' => base64_encode($secretFormula)]);
        $digest = md5($npwp);
        $party->npwp_hash = $digest;
        PHP;

    $signatures = ruleSignatures(new UnkeyedHashOfSensitiveValueRule, 'modules/Hcm/Application/Services/X.php', $code);

    expect($signatures)->toBe([
        "nik_hash = hash('sha256',\$nik)",
        'formula_payload_encrypted = base64_encode($secretFormula)',
        'npwp_hash = $digest',
    ]);
});

it('does not report keyed hashes, real encryption or non-sensitive columns', function (): void {
    $code = <<<'PHP'
        <?php
        Employee::create([
            'nik_blind_index' => hash_hmac('sha256', $nik, config('app.pepper')),
            'npwp_encrypted' => Crypt::encryptString($npwp),
            'passport_hash' => hash('sha256', $previousHash.$eventPayload),
            'document_checksum' => hash('sha256', $contents),
        ]);
        PHP;

    $signatures = ruleSignatures(new UnkeyedHashOfSensitiveValueRule, 'modules/Hcm/Application/Services/X.php', $code);

    expect($signatures)->toBe([]);
});

it('classifies sensitive column names', function (string $name, bool $isSensitive): void {
    expect(UnkeyedHashOfSensitiveValueRule::isSensitiveName($name))->toBe($isSensitive);
})->with([
    ['nik', true],
    ['nik_hash', true],
    ['npwpNumber', true],
    ['passport_number', true],
    ['webhook_secret', true],
    ['allegation_encrypted', true],
    ['vehicle_passport_hash', false],
    ['technician_id', false],
]);
