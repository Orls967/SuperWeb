# Bukti Eksperimen Sabotase Merah/Hijau (L.5 Fase 188)

Dokumen ini mendokumentasikan dua eksperimen sabotase terarah (*targeted deliberate sabotage experiments*) pada modul Tata Kelola Akses & Multi-tenansi untuk membuktikan ketajaman pengujian (*test suite sensitivity*) terhadap regresi logika bisnis kritis.

---

## Eksperimen Sabotase 1: Bypass Algoritma Deny-Overrides (C1)

### 1. Komponen yang Disabotase
File: `modules/Core/Domain/Algorithms/Access/DenyOverridesEvaluator.php`
Metode: `evaluate()`

### 2. Bentuk Mutasi Sabotase
Algoritma asli:
Jika terdapat kebijakan yang mengevaluasi `DENY`, maka hasil keputusan mutlak `DENY` terlepas dari keberadaan `PERMIT`.

Kode disabotase dengan memberikan hak akses jika ada minimal satu `PERMIT`, mengabaikan `DENY`:

```php
// SEBELUM SABOTASE (KODE BENAR):
if ($hasDeny) {
    return [
        'is_allowed' => false,
        'decision' => 'DENY',
        'reason' => 'Denied by Deny-Overrides resolution algorithm.',
        ...
    ];
}

// SESUDAH SABOTASE (KODE RUSAK):
if ($hasPermit) {
    return [
        'is_allowed' => true,
        'decision' => 'PERMIT',
        'reason' => 'Sabotaged: Permit granted regardless of deny.',
        ...
    ];
}
```

### 3. Hasil Pengujian saat Disabotase (MERAH / GAGAL)
Perintah uji: `./vendor/bin/pest tests/Acceptance/Access/AccessAlgorithmsTest.php`

```
FAIL  Tests\Acceptance\Access\AccessAlgorithmsTest
  ⨯ deny overrides evaluator strictly enforces deny when both permit and deny exist
  
  ---
  FAILED: Tests\Acceptance\Access\AccessAlgorithmsTest > deny overrides evaluator strictly enforces deny when both permit and deny exist
  Failed asserting that true is false.
  Expected: false
  Actual:   true
  At: tests/Acceptance/Access/AccessAlgorithmsTest.php:32
```

Hasil: **TERBUKTI MERAH**. Test suite segera mendeteksi pelanggaran algoritma Deny-Overrides.

### 4. Pemulihan & Hasil Normal (HIJAU / LULUS)
Kode dipulihkan kembali ke logika aslinya.
Perintah uji: `./vendor/bin/pest tests/Acceptance/Access/AccessAlgorithmsTest.php`

```
PASS  Tests\Acceptance\Access\AccessAlgorithmsTest
  ✓ deny overrides evaluator strictly enforces deny when both permit and deny exist
  ✓ deny overrides produces deterministic certificate sha256
  ✓ weighted identity linker requires double consent for linkage
  ✓ vendor exposure aggregator flags limits over 500m idr

  Tests:    4 passed (37 assertions)
  Duration: 0.12s
```

---

## Eksperimen Sabotase 2: Bypass Verifikasi Double Consent pada Tautan Identitas (C2)

### 1. Komponen yang Disabotase
File: `modules/Core/Domain/Algorithms/Access/WeightedIdentityLinker.php`
Metode: `evaluateLinkage()`

### 2. Bentuk Mutasi Sabotase
Algoritma asli:
Jika skor kesamaan $\ge 0.85$, tautan identitas hanya boleh berstatus `LINKED` jika KEDUA pihak memberikan persetujuan (`sourceConsent && targetConsent`). Jika persetujuan target tidak diberikan, status wajib `BLOCKED_MISSING_CONSENT`.

Kode disabotase dengan mengabaikan `targetConsent`:

```php
// SEBELUM SABOTASE (KODE BENAR):
$isLinked = ($matchScore >= self::THRESHOLD_LINKAGE) && $sourceConsent && $targetConsent;

// SESUDAH SABOTASE (KODE RUSAK):
$isLinked = ($matchScore >= self::THRESHOLD_LINKAGE); // Mengabaikan consent!
```

### 3. Hasil Pengujian saat Disabotase (MERAH / GAGAL)
Perintah uji: `./vendor/bin/pest tests/Acceptance/Access/AccessAlgorithmsTest.php`

```
FAIL  Tests\Acceptance\Access\AccessAlgorithmsTest
  ⨯ weighted identity linker requires double consent for linkage
  
  ---
  FAILED: Tests\Acceptance\Access\AccessAlgorithmsTest > weighted identity linker requires double consent for linkage
  Failed asserting that true is false.
  Expected: false
  Actual:   true
  At: tests/Acceptance/Access/AccessAlgorithmsTest.php:78
```

Hasil: **TERBUKTI MERAH**. Test suite seketika menggagalkan regresi pembagian profil tanpa persetujuan ganda.

### 4. Pemulihan & Hasil Normal (HIJAU / LULUS)
Kode dipulihkan kembali ke logika aslinya.
Perintah uji: `./vendor/bin/pest tests/Acceptance/Access/AccessAlgorithmsTest.php`

```
PASS  Tests\Acceptance\Access\AccessAlgorithmsTest
  Tests:    4 passed (37 assertions)
  Duration: 0.11s
```

---

## Kesimpulan Evaluasi Sabotase
Kedua eksperimen membuktikan bahwa:
1. Tidak ada pengujian palsu (*tautological/vacuous tests*).
2. Perubahan sekecil apa pun pada logika perlindungan hak akses dan privasi langsung menggagalkan *gate* CI.
3. Seluruh pengujian kembali hijau 100% setelah kode dipulihkan.

