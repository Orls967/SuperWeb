<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Core\Domain\Models\DocumentAttachment;
use Modules\Core\Domain\Models\DocumentSequence;

uses(RefreshDatabase::class);

test('document numbering produces gapless sequential numbers with reset', function () {
    $service = app(DocumentNumberingInterface::class);

    $num1 = $service->nextNumber(
        entityCode: 'SRX',
        documentType: 'INV',
        resetMonthly: true
    );

    $num2 = $service->nextNumber(
        entityCode: 'SRX',
        documentType: 'INV',
        resetMonthly: true
    );

    $num3 = $service->nextNumber(
        entityCode: 'SRX',
        documentType: 'INV',
        resetMonthly: true
    );

    $year = now()->format('Y');
    $month = now()->format('m');
    $period = "{$year}{$month}";

    expect($num1)->toBe("SRX/INV/{$period}-00001")
        ->and($num2)->toBe("SRX/INV/{$period}-00002")
        ->and($num3)->toBe("SRX/INV/{$period}-00003");

    $seq = DocumentSequence::where('entity_code', 'SRX')
        ->where('document_type', 'INV')
        ->first();

    expect($seq->current_number)->toBe(3);
});

test('document numbering handles custom prefixes and yearly resets', function () {
    $service = app(DocumentNumberingInterface::class);

    $claimNum = $service->nextNumber(
        entityCode: 'LGX',
        documentType: 'CLAIM',
        resetMonthly: false,
        customPrefix: 'CLM/{YYYY}/'
    );

    $year = now()->format('Y');
    expect($claimNum)->toBe("CLM/{$year}/00001");
});

test('document store safely stores uploaded file with checksum and retention', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $service = app(DocumentStoreInterface::class);
    $content = 'Sample contract terms and conditions content for testing';
    $file = UploadedFile::fake()->createWithContent('contract_2026.pdf', $content);

    $doc = $service->store(
        file: $file,
        filename: 'contract_2026.pdf',
        documentType: 'CONTRACT',
        documentable: null,
        uploadedBy: $user,
        metadata: ['contract_ref' => 'REF-99'],
        retentionYears: 5
    );

    expect($doc)->toBeInstanceOf(DocumentAttachment::class)
        ->and($doc->original_filename)->toBe('contract_2026.pdf')
        ->and($doc->document_type)->toBe('CONTRACT')
        ->and($doc->uploaded_by)->toBe($user->id)
        ->and($doc->checksum_sha256)->toBe(hash('sha256', $content))
        ->and($doc->retention_until)->not->toBeNull();

    expect(Storage::disk('local')->exists($doc->stored_path))->toBeTrue();
    expect($service->verifyChecksum($doc))->toBeTrue();
});

test('document store rejects executable or dangerous file extensions', function () {
    Storage::fake('local');
    $service = app(DocumentStoreInterface::class);

    expect(fn () => $service->store(
        file: '<?php echo "bad"; ?>',
        filename: 'exploit.php',
        documentType: 'MALWARE'
    ))->toThrow(InvalidArgumentException::class, 'berbahaya dan tidak diizinkan');

    expect(fn () => $service->store(
        file: 'echo bad',
        filename: 'script.sh',
        documentType: 'MALWARE'
    ))->toThrow(InvalidArgumentException::class, 'berbahaya dan tidak diizinkan');
});

test('document store detects corrupted or tampered file checksum', function () {
    Storage::fake('local');
    $service = app(DocumentStoreInterface::class);

    $doc = $service->store(
        file: 'Original genuine content',
        filename: 'receipt.txt',
        documentType: 'RECEIPT'
    );

    expect($service->verifyChecksum($doc))->toBeTrue();

    // Tamper with stored file content directly
    Storage::disk('local')->put($doc->stored_path, 'Tampered corrupted content!');

    expect($service->verifyChecksum($doc))->toBeFalse();
});
