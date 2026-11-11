# Laporan Uji Sabotase L.5: Fase 55 (API v2, Webhook HMAC & Token Bucket)

**Tanggal:** 2026-11-12  
**Worktree:** `sw-w8-p4`  
**Pelaksana:** [W8-P4.2] Fase 55  
**Baseline SHA:** `35f5371f731f4a1f6a19cd2a0389943905b68a22`  

---

## 1. Tujuan & Desain Sabotase

Pengujian sabotase bertujuan membuktikan bahwa rangkaian pengujian (test suite) dan sertifikat kriptografis SHA-256 benar-benar mengawal integritas logika bisnis, bukan sekadar tes hampa (*tautological pass*).

Dua komponen kritis disabotase secara independen:
1. **Sabotase 1 (B1): IntegerTokenBucket Clock Regression Guard**
   - Menghilangkan validasi `nowMs < lastMs` (membiarkan regresi jam tanpa pengecualian).
2. **Sabotase 2 (B2): WebhookSignatureVerifier Constant-Time Comparator**
   - Memaksa status verifikasi tanda tangan HMAC selalu bernilai `STATUS_OK`.

---

## 2. Eksperimen Sabotase 1: IntegerTokenBucket

### A. Titik Sabotase
Berkas: `modules/Core/Domain/Algorithms/Api/IntegerTokenBucket.php`
```diff
-        if ($nowMs < $lastMs) {
-            throw new DomainException("CLOCK_REGRESSION: Waktu permintaan ({$nowMs} ms) mendahului stempel terakhir ({$lastMs} ms).");
-        }
+        if ($nowMs < $lastMs) {
+            // SABOTAGE L.5: Mengabaikan regresi jam
+        }
```

### B. Bukti Merah (*RED Trace*)
```
   FAIL  Tests\Acceptance\ApiV2\ApiV2AlgorithmsTest
  ⨯ it verifies exact prompt vectors for B1 IntegerTokenBucket, B2 Webh… 0.02s  
  ✓ it rejects tampered SHA-256 certificates for B1, B2, and B3 algorithms
  ────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Acceptance\ApiV2\ApiV2AlgorithmsTest > it verifies exact pr…   
  Exception "DomainException" not thrown.

  at tests/Acceptance/ApiV2/ApiV2AlgorithmsTest.php:65
     61    ->and($denyAt2000['retry_after_ms'])->toBe(500);
     62 
     63    // Clock regression must throw CLOCK_REGRESSION
     64    expect(fn () => $bucket->consume($tokens, 2000, 1999, 5, 2))
  ➜  65        ->toThrow(DomainException::class, 'CLOCK_REGRESSION');
     66 

  Tests:    1 failed, 1 passed (22 assertions)
```

### C. Pemulihan (*GREEN*)
Kode dikembalikan ke bentuk semula.
```bash
$ ./vendor/bin/pest tests/Acceptance/ApiV2/ApiV2AlgorithmsTest.php
   PASS  Tests\Acceptance\ApiV2\ApiV2AlgorithmsTest
  ✓ it verifies exact prompt vectors for B1 IntegerTokenBucket, B2 Webh… 0.01s  
  ✓ it rejects tampered SHA-256 certificates for B1, B2, and B3 algorithms

  Tests:    2 passed (59 assertions)
  Duration: 0.04s
```

---

## 3. Eksperimen Sabotase 2: WebhookSignatureVerifier & Certificate Verifier

### A. Titik Sabotase
Berkas: `modules/Core/Domain/Algorithms/Api/WebhookSignatureVerifier.php`
```diff
             } else {
-                $status = self::STATUS_MISMATCH;
-                foreach ($computedV1s as $idx => $expectedHex) {
-                    foreach ($receivedV1s as $candidateHex) {
-                        if (hash_equals($expectedHex, strtolower($candidateHex))) {
-                            $status = self::STATUS_OK;
-                            $matchedIndex = $idx;
-                            break 2;
-                        }
-                    }
-                }
+                $status = self::STATUS_OK; // SABOTAGE L.5: Bypassing signature verification
                 $steps[] = "compare_hmac_constant_time => {$status}";
             }
```

### B. Bukti Merah (*RED Trace*)
```
   FAIL  Tests\Acceptance\ApiV2\ApiV2AlgorithmsTest
  ⨯ it verifies exact prompt vectors for B1 IntegerTokenBucket, B2 Webh… 0.01s  
  ✓ it rejects tampered SHA-256 certificates for B1, B2, and B3 algorithms
  ────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Acceptance\ApiV2\ApiV2AlgorithmsTest > it…   DomainException   
  WEBHOOK_CERT_INVARIANT_FAILED: Status OK membutuhkan delta <= jendela dan matched_secret_index valid.

  at modules/Core/Domain/Algorithms/Api/WebhookSignatureCertificateVerifier.php:57
     53         if ($status === WebhookSignatureVerifier::STATUS_OK) {
     54             $delta = $result['delta_seconds'] ?? null;
     55             $window = (int) ($input['window_seconds'] ?? 300);
     56             if (! is_int($delta) || $delta > $window || ($result['matched_secret_index'] ?? null) === null) {
  ➜  57                 throw new DomainException('WEBHOOK_CERT_INVARIANT_FAILED: Status OK membutuhkan delta <= jendela dan matched_secret_index valid.');
     58             }
     59         }

  Tests:    1 failed, 1 passed (24 assertions)
```
- **Analisis Kritis:** Verifikasi sertifikat kanonik `WebhookSignatureCertificateVerifier` mendeteksi bahwa meskipun algoritma mengembalikan `STATUS_OK`, invarian struktural dilanggar (`matched_secret_index` tidak bernilai integer karena tidak ada pencocokan HMAC asli). Ini membuktikan proteksi ganda *runtime certificate assertion*.

### C. Pemulihan (*GREEN*)
Kode dikembalikan ke implementasi `hash_equals` konstan-waktu.
```bash
$ ./vendor/bin/pest tests/Acceptance/ApiV2/ApiV2AlgorithmsTest.php
   PASS  Tests\Acceptance\ApiV2\ApiV2AlgorithmsTest
  ✓ it verifies exact prompt vectors for B1 IntegerTokenBucket, B2 Webh… 0.01s  
  ✓ it rejects tampered SHA-256 certificates for B1, B2, and B3 algorithms

  Tests:    2 passed (59 assertions)
  Duration: 0.04s
```

---

## 4. Kesimpulan
Seluruh sabotase terbukti memicu kegagalan deterministik baik pada level pengujian unit/vektor maupun level sertifikat SHA-256. Tidak ada toleransi salah-positif (*false positive*), membuktikan keandalan fondasi sistem.

