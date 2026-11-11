<?php

declare(strict_types=1);

use Modules\Core\Domain\Algorithms\Api\IntegerTokenBucket;
use Modules\Core\Domain\Algorithms\Api\TokenBucketCertificateVerifier;
use Modules\Core\Domain\Algorithms\Api\WebhookSignatureCertificateVerifier;
use Modules\Core\Domain\Algorithms\Api\WebhookSignatureVerifier;
use Modules\Core\Domain\Algorithms\Edi\EdiInterchangeCertificateVerifier;
use Modules\Core\Domain\Algorithms\Edi\EdiInterchangeCodec;

it('verifies exact prompt vectors for B1 IntegerTokenBucket, B2 WebhookSignatureVerifier, and B3 EdiInterchangeCodec', function (): void {
    // ── B1: IntegerTokenBucket (cap 5, rate 2/s, full start at t = 0) ───────────
    $bucket = new IntegerTokenBucket;
    $bucketVerifier = new TokenBucketCertificateVerifier;
    $tokens = 5000;
    $lastMs = 0;

    $remainingAtT0 = [];
    for ($i = 0; $i < 5; $i++) {
        $res = $bucket->consume($tokens, $lastMs, 0, 5, 2);
        $bucketVerifier->verify($res);
        expect($res['allowed'])->toBeTrue();
        $tokens = $res['remaining_milli_tokens'];
        $lastMs = $res['last_ms'];
        $remainingAtT0[] = $res['remaining_tokens'];
    }
    expect($remainingAtT0)->toBe([4, 3, 2, 1, 0]);

    $deny1 = $bucket->consume($tokens, $lastMs, 0, 5, 2);
    $bucketVerifier->verify($deny1);
    expect($deny1['allowed'])->toBeFalse()
        ->and($deny1['retry_after_ms'])->toBe(500);

    // t = 500ms -> +1000 milli-tokens -> OK (remaining 0), next DENY retry_after_ms 500
    $okAt500 = $bucket->consume($tokens, $lastMs, 500, 5, 2);
    $bucketVerifier->verify($okAt500);
    expect($okAt500['allowed'])->toBeTrue()
        ->and($okAt500['remaining_tokens'])->toBe(0);
    $tokens = $okAt500['remaining_milli_tokens'];
    $lastMs = $okAt500['last_ms'];

    $denyAt500 = $bucket->consume($tokens, $lastMs, 500, 5, 2);
    expect($denyAt500['allowed'])->toBeFalse()
        ->and($denyAt500['retry_after_ms'])->toBe(500);

    // t = 2000ms (+1500ms * 2 = +3000 milli-tokens) -> 3 OK (2, 1, 0), 4th DENY 500ms
    $okAt2000 = [];
    for ($i = 0; $i < 3; $i++) {
        $res = $bucket->consume($tokens, $lastMs, 2000, 5, 2);
        $bucketVerifier->verify($res);
        expect($res['allowed'])->toBeTrue();
        $tokens = $res['remaining_milli_tokens'];
        $lastMs = $res['last_ms'];
        $okAt2000[] = $res['remaining_tokens'];
    }
    expect($okAt2000)->toBe([2, 1, 0]);

    $denyAt2000 = $bucket->consume($tokens, $lastMs, 2000, 5, 2);
    expect($denyAt2000['allowed'])->toBeFalse()
        ->and($denyAt2000['retry_after_ms'])->toBe(500);

    // Clock regression must throw CLOCK_REGRESSION
    expect(fn () => $bucket->consume($tokens, 2000, 1999, 5, 2))
        ->toThrow(DomainException::class, 'CLOCK_REGRESSION');

    // ── B2: WebhookSignatureVerifier ───────────────────────────────────────────
    $signer = new WebhookSignatureVerifier;
    $sigVerifier = new WebhookSignatureCertificateVerifier;
    $body1500 = '{"id":"evt_1","amount":1500}';
    $header1500 = $signer->sign($body1500, 'whsec_test', 1700000000);
    expect($header1500)->toBe('t=1700000000,v1=0d74ebc95953607011bb60f2d3651900e64d5d82aeb6b91d62eb922cf3ac68eb');

    $okSig = $signer->verify($header1500, $body1500, ['whsec_test'], 1700000100, 300);
    $sigVerifier->verify($okSig);
    expect($okSig['status'])->toBe(WebhookSignatureVerifier::STATUS_OK);

    $expiredSig = $signer->verify($header1500, $body1500, ['whsec_test'], 1700000400, 300);
    $sigVerifier->verify($expiredSig);
    expect($expiredSig['status'])->toBe(WebhookSignatureVerifier::STATUS_EXPIRED);

    $body1501 = '{"id":"evt_1","amount":1501}';
    $mismatchSig = $signer->verify($header1500, $body1501, ['whsec_test'], 1700000100, 300);
    $sigVerifier->verify($mismatchSig);
    expect($mismatchSig['status'])->toBe(WebhookSignatureVerifier::STATUS_MISMATCH)
        ->and($signer->sign($body1501, 'whsec_test', 1700000000))
        ->toBe('t=1700000000,v1=d3abc86c195d130c29c68ecb9a3ec4a9a44f20ecc664e8087dc359dae2d9243a');

    $headerRotated = $signer->sign($body1500, 'whsec_old', 1700000000, ['whsec_test']);
    expect($headerRotated)->toBe('t=1700000000,v1=c94593a0591a69cdb9f38aadd8992a17967e4366d63f37ab2d2738081dd98981,v1=0d74ebc95953607011bb60f2d3651900e64d5d82aeb6b91d62eb922cf3ac68eb');
    $rotatedOk = $signer->verify($headerRotated, $body1500, ['whsec_test'], 1700000100, 300);
    expect($rotatedOk['status'])->toBe(WebhookSignatureVerifier::STATUS_OK);

    $malformedSig = $signer->verify('v1=0d74ebc95953607011bb60f2d3651900e64d5d82aeb6b91d62eb922cf3ac68eb', $body1500, ['whsec_test'], 1700000100);
    expect($malformedSig['status'])->toBe(WebhookSignatureVerifier::STATUS_MALFORMED);

    // ── B3: EdiInterchangeCodec (EDIFACT + X12 subset) ─────────────────────────
    $codec = new EdiInterchangeCodec;
    $ediVerifier = new EdiInterchangeCertificateVerifier;

    $edifactRaw = "UNB+UNOC:3+SENDER01:ZZ+RECEIVER01:ZZ+261108:1030+IC0001'UNH+1+ORDERS:D:96A:UN'BGM+220+PO12345+9'DTM+137:20261108:102'NAD+BY+BUYER?+01::9'LIN+1++ITEM-A:SRV'QTY+21:10'LIN+2++ITEM-B:SRV'QTY+21:5'UNS+S'CNT+2:2'UNT+11+1'UNZ+1+IC0001'";
    $doc = $codec->decode($edifactRaw);
    $ediVerifier->verify($doc);
    expect($doc->isValid)->toBeTrue()
        ->and($doc->segmentCount)->toBe(13)
        ->and($doc->messageSegmentCount)->toBe(11)
        ->and($doc->documentTypeCode)->toBe('220')
        ->and($doc->documentNumber)->toBe('PO12345')
        ->and($doc->buyerIdentifier)->toBe('BUYER+01')
        ->and(count($doc->lineItems))->toBe(2)
        ->and($doc->lineItems[0]['quantity'])->toBe(10)
        ->and($doc->lineItems[1]['quantity'])->toBe(5)
        ->and($codec->encode($doc))->toBe($edifactRaw)
        ->and($doc->acknowledgmentAction)->toBe('7');

    $badCountEdifact = str_replace('UNT+11+1', 'UNT+12+1', $edifactRaw);
    $docBadCount = $codec->decode($badCountEdifact);
    expect($docBadCount->isValid)->toBeFalse()
        ->and($docBadCount->errorCodes)->toContain('UNT_COUNT_MISMATCH')
        ->and($docBadCount->acknowledgmentAction)->toBe('4');

    $badRefEdifact = str_replace('UNT+11+1', 'UNT+11+2', $edifactRaw);
    $docBadRef = $codec->decode($badRefEdifact);
    expect($docBadRef->isValid)->toBeFalse()
        ->and($docBadRef->errorCodes)->toContain('UNH_UNT_REFERENCE_MISMATCH')
        ->and($docBadRef->acknowledgmentAction)->toBe('4');

    $missingQtyEdifact = str_replace(["QTY+21:5'", "UNT+11+1'"], ['', "UNT+10+1'"], $edifactRaw);
    $docMissingQty = $codec->decode($missingQtyEdifact);
    expect($docMissingQty->isValid)->toBeFalse()
        ->and($docMissingQty->errorCodes)->toContain('LIN_WITHOUT_QTY');

    // ASC X12 850 + 997 subset
    $x12Raw = 'ST*850*0001~BEG*00*NE*PO12345**20261108~PO1*1*10*EA*500**VP*ITEM-A~PO1*2*5*EA*1200**VP*ITEM-B~CTT*2~SE*6*0001~';
    $x12Doc = $codec->decode($x12Raw);
    $ediVerifier->verify($x12Doc);
    expect($x12Doc->isValid)->toBeTrue()
        ->and($x12Doc->messageSegmentCount)->toBe(6)
        ->and($codec->encode($x12Doc))->toBe($x12Raw)
        ->and($x12Doc->acknowledgmentPayload)->toBe('AK1*PO*1~AK2*850*0001~AK5*A~AK9*A*1*1*1~');

    $x12BadCount = $codec->decode(str_replace('SE*6*0001~', 'SE*5*0001~', $x12Raw));
    expect($x12BadCount->isValid)->toBeFalse()
        ->and($x12BadCount->acknowledgmentPayload)->toContain('AK5*R*5')
        ->and($x12BadCount->acknowledgmentPayload)->toContain('AK9*R*1*1*0');

    $x12BadCtrl = $codec->decode(str_replace('SE*6*0001~', 'SE*6*0002~', $x12Raw));
    expect($x12BadCtrl->isValid)->toBeFalse()
        ->and($x12BadCtrl->acknowledgmentPayload)->toContain('AK5*R*4');
});

it('rejects tampered SHA-256 certificates for B1, B2, and B3 algorithms', function (): void {
    $bucket = new IntegerTokenBucket;
    $bucketVerifier = new TokenBucketCertificateVerifier;
    $res = $bucket->consume(5000, 0, 0, 5, 2);
    $res['certificate']['sha256'] = str_repeat('0', 64);
    expect(fn () => $bucketVerifier->verify($res))->toThrow(DomainException::class);

    $signer = new WebhookSignatureVerifier;
    $sigVerifier = new WebhookSignatureCertificateVerifier;
    $sig = $signer->verify($signer->sign('{"a":1}', 'whsec_test', 1700000000), '{"a":1}', ['whsec_test'], 1700000010);
    $sig['certificate']['sha256'] = str_repeat('f', 64);
    expect(fn () => $sigVerifier->verify($sig))->toThrow(DomainException::class);

    $codec = new EdiInterchangeCodec;
    $ediVerifier = new EdiInterchangeCertificateVerifier;
    $doc = $codec->decode("UNB+UNOC:3+S:ZZ+R:ZZ+261108:1030+IC1'UNH+1+ORDERS:D:96A:UN'BGM+220+PO1+9'LIN+1++A:SRV'QTY+21:1'UNT+5+1'UNZ+1+IC1'");
    $doc->certificate['sha256'] = str_repeat('a', 64);
    expect(fn () => $ediVerifier->verify($doc))->toThrow(DomainException::class);
});

