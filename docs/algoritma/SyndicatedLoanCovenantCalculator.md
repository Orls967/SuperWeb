# Spesifikasi Algoritma Bersertifikat D7: SyndicatedLoanCovenantCalculator

## 1. Identitas & Tujuan Algoritma
- **Kode Algoritma**: `D7-SLCC`
- **Nama Kelas**: `Modules\EnterpriseFinance\Domain\Algorithms\SyndicatedLoanCovenantCalculator`
- **Tujuan**: Menghitung 4 rasio covenant finansial utama (DSCR, DER, ISCR, Current Ratio) secara deterministik tanpa floating-point menggunakan aritmetika bilangan bulat satuan permille ($1.000\text{ permille} = 100\%$), serta menerbitkan sertifikat verifikasi digital SHA-256.

---

## 2. Definisi Masukan (Input) & Keluaran (Output)

### Masukan (`SyndicatedLoanCovenantInputDTO`):
- `facility_code` (string): Kode identifikasi fasilitas kredit sindikasi.
- `evaluation_period` (string): Periode evaluasi (format YYYY-Qx atau YYYY-MM).
- `ebitda_minor` (int): Laba sebelum bunga, pajak, depresiasi, dan amortisasi dalam satuan sen/minor.
- `tax_paid_minor` (int): Pajak penghasilan yang telah dibayarkan.
- `debt_service_minor` (int): Total angsuran pokok dan bunga utang yang jatuh tempo.
- `total_interest_bearing_debt_minor` (int): Total utang berbunga (pokok pinjaman sindikasi + obligasi).
- `total_equity_minor` (int): Total ekuitas pemegang saham konglomerasi.
- `ebit_minor` (int): Laba sebelum bunga dan pajak.
- `interest_expense_minor` (int): Beban bunga pinjaman berjalan.
- `current_assets_minor` (int): Total aset lancar grup.
- `current_liabilities_minor` (int): Total liabilitas jangka pendek grup.
- `covenant_thresholds` (array): Ambang batas perjanjian kredit dalam permille:
  - `min_dscr_permille` (int, default: 1250)
  - `max_der_permille` (int, default: 2500)
  - `min_iscr_permille` (int, default: 2000)
  - `min_current_ratio_permille` (int, default: 1100)

### Keluaran (`SyndicatedLoanCovenantResultDTO`):
- `dscr_permille` (int): Rasio DSCR terhitung.
- `der_permille` (int): Rasio DER terhitung.
- `iscr_permille` (int): Rasio ISCR terhitung.
- `current_ratio_permille` (int): Rasio Current Ratio terhitung.
- `is_dscr_compliant` (bool)
- `is_der_compliant` (bool)
- `is_iscr_compliant` (bool)
- `is_current_ratio_compliant` (bool)
- `is_fully_compliant` (bool)
- `certificate` (`SyndicatedLoanCovenantCertificate`): Objek sertifikat berisi payload dan signature SHA-256.

---

## 3. Logika Perhitungan Matematis
```
1. DSCR = intdiv((ebitda_minor - tax_paid_minor) * 1000, max(1, debt_service_minor))
2. DER  = intdiv(total_interest_bearing_debt_minor * 1000, max(1, total_equity_minor))
3. ISCR = intdiv(ebit_minor * 1000, max(1, interest_expense_minor))
4. CR   = intdiv(current_assets_minor * 1000, max(1, current_liabilities_minor))

5. is_dscr_compliant = (DSCR >= min_dscr_permille)
6. is_der_compliant  = (DER <= max_der_permille)
7. is_iscr_compliant = (ISCR >= min_iscr_permille)
8. is_cr_compliant   = (CR >= min_current_ratio_permille)
9. is_fully_compliant = (is_dscr_compliant && is_der_compliant && is_iscr_compliant && is_cr_compliant)
```

---

## 4. Struktur Sertifikat Kriptografis
Payload sertifikat disusun sebagai JSON deterministik terurut:
```json
{
  "facility_code": "SYND-2026-CONSORTIUM-01",
  "period": "2026-Q3",
  "dscr_permille": 1420,
  "der_permille": 1850,
  "iscr_permille": 2300,
  "current_ratio_permille": 1350,
  "is_fully_compliant": true,
  "timestamp": "2026-11-11T07:00:00Z"
}
```
Signature dihitung melalui `hash('sha256', $canonicalJsonString)`.
Verifier `SyndicatedLoanCovenantCertificateVerifier::verify($certificate)` merekonstruksi hash dan melempar `InvalidCertificateException` jika terjadi ketidakcocokan sekecil apa pun.
