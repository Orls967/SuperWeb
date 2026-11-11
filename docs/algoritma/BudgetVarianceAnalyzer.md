# Spesifikasi Algoritma Bersertifikat D9: BudgetVarianceAnalyzer

## 1. Identitas & Tujuan Algoritma
- **Kode Algoritma**: `D9-BVA`
- **Nama Kelas**: `Modules\EnterpriseFinance\Domain\Algorithms\BudgetVarianceAnalyzer`
- **Tujuan**: Menganalisis varians antara anggaran yang dialokasikan (*budgeted*) dan pengeluaran aktual (*actual spend*) dalam satuan basis points / permille ($1.000\text{ permille} = 100\%$), mengklasifikasikan status varians (*Favorable*, *Unfavorable*, *Breached*), serta menerbitkan sertifikat digital SHA-256.

---

## 2. Definisi Masukan (Input) & Keluaran (Output)

### Masukan (`BudgetVarianceInputDTO`):
- `department_code` (string): Kode departemen atau pusat biaya.
- `fiscal_year` (string): Tahun fiskal anggaran (mis. 2026).
- `allocated_budget_minor` (int): Pagu anggaran yang dialokasikan dalam sen/minor IDR.
- `actual_spent_minor` (int): Total pengeluaran aktual yang terealisasi.
- `encumbered_minor` (int): Nilai komitmen belanja yang belum cair.
- `variance_breach_threshold_permille` (int, default: 100): Batas toleransi overspend (mis. 100 permille = 10%).

### Keluaran (`BudgetVarianceResultDTO`):
- `variance_amount_minor` (int): $\text{Allocated} - (\text{Actual} + \text{Encumbered})$.
- `utilization_permille` (int): $\text{intdiv}((\text{Actual} + \text{Encumbered}) \times 1.000, \max(1, \text{Allocated}))$.
- `variance_permille` (int): Selisih pemakaian terhadap $1.000$ permille.
- `classification` (string): `FAVORABLE` (hemat/di bawah pagu), `ON_TRACK` (90–100%), `UNFAVORABLE_WARNING` (100–110%), `CRITICAL_BREACH` (>110%).
- `is_breached` (bool): True jika pemakaian melampaui alokasi ditambah batas toleransi.
- `certificate` (`BudgetVarianceCertificate`): Objek sertifikat verifikasi digital SHA-256.

---

## 3. Logika Perhitungan Matematis
```
1. total_committed = actual_spent_minor + encumbered_minor
2. variance_amount = allocated_budget_minor - total_committed
3. utilization_permille = intdiv(total_committed * 1000, max(1, allocated_budget_minor))

4. Jika total_committed <= allocated_budget_minor:
     classification = (utilization_permille >= 900) ? 'ON_TRACK' : 'FAVORABLE'
     is_breached = false
   Lainnya:
     excess_permille = utilization_permille - 1000
     Jika excess_permille > variance_breach_threshold_permille:
       classification = 'CRITICAL_BREACH'
       is_breached = true
     Lainnya:
       classification = 'UNFAVORABLE_WARNING'
       is_breached = false
```

---

## 4. Struktur Sertifikat Kriptografis
Payload sertifikat disusun sebagai JSON deterministik:
```json
{
  "department": "DEP-MANUF-OPERATIONS",
  "fiscal_year": "2026",
  "allocated_minor": 500000000000,
  "committed_minor": 485000000000,
  "utilization_permille": 970,
  "classification": "ON_TRACK",
  "is_breached": false,
  "timestamp": "2026-11-11T07:00:00Z"
}
```
Signature dihitung melalui `hash('sha256', $canonicalJsonString)`. Verifier `BudgetVarianceCertificateVerifier::verify($certificate)` memvalidasi signature tersebut.
