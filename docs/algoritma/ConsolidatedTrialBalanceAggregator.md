# Spesifikasi Algoritma Bersertifikat D8: ConsolidatedTrialBalanceAggregator

## 1. Identitas & Tujuan Algoritma
- **Kode Algoritma**: `D8-CTBA`
- **Nama Kelas**: `Modules\EnterpriseFinance\Domain\Algorithms\ConsolidatedTrialBalanceAggregator`
- **Tujuan**: Mengagregasikan neraca saldo (Trial Balance) dari jamak entitas anak konglomerasi, mengelompokkan saldo debit dan kredit per akun kanonikal grup, memvalidasi zero-sum delta balance, serta menerbitkan sertifikat verifikasi digital SHA-256.

---

## 2. Definisi Masukan (Input) & Keluaran (Output)

### Masukan (`ConsolidatedTrialBalanceInputDTO`):
- `consolidation_period` (string): Periode pelaporan konsolidasi (mis. YYYY-MM atau YYYY-Qx).
- `reporting_currency` (string): Mata uang dasar pelaporan grup (default: IDR).
- `entity_balances` (array of `EntityTrialBalanceDTO`):
  - `entity_code` (string): Kode entitas anak.
  - `entity_name` (string): Nama resmi entitas.
  - `account_balances` (array of `AccountBalanceDTO`):
    - `canonical_account_code` (string): Kode akun grup terstandarisasi.
    - `debit_minor` (int): Total saldo debit akun dalam minor unit IDR.
    - `credit_minor` (int): Total saldo kredit akun dalam minor unit IDR.

### Keluaran (`ConsolidatedTrialBalanceResultDTO`):
- `period` (string)
- `total_entities_count` (int)
- `total_debit_minor` (int): Agregasi seluruh saldo debit.
- `total_credit_minor` (int): Agregasi seluruh saldo kredit.
- `delta_balance_minor` (int): Selisih $\Sigma \text{Debit} - \Sigma \text{Kredit}$.
- `is_zero_sum_balanced` (bool): True jika $\text{delta} == 0$.
- `aggregated_accounts` (array of `AggregatedAccountSummaryDTO`): Saldo bersih per akun grup.
- `certificate` (`ConsolidatedTrialBalanceCertificate`): Sertifikat integritas dengan hash SHA-256.

---

## 3. Logika Agregasi & Validasi Zero-Sum
```
1. Inisialisasi total_debit = 0, total_credit = 0, account_map = []
2. Untuk setiap entitas dalam entity_balances:
     Untuk setiap akun dalam account_balances:
       total_debit += akun.debit_minor
       total_credit += akun.credit_minor
       account_map[akun.canonical_account_code].debit += akun.debit_minor
       account_map[akun.canonical_account_code].credit += akun.credit_minor
3. delta = total_debit - total_credit
4. is_zero_sum_balanced = (delta === 0)
5. Jika tidak zero-sum dan mode strict aktif, lempar UnbalancedTrialBalanceException.
```

---

## 4. Struktur Sertifikat Kriptografis
Payload sertifikat disusun sebagai JSON deterministik:
```json
{
  "period": "2026-10",
  "currency": "IDR",
  "entity_count": 5,
  "total_debit_minor": 1850000000000,
  "total_credit_minor": 1850000000000,
  "delta_minor": 0,
  "is_balanced": true,
  "timestamp": "2026-11-11T07:00:00Z"
}
```
Signature dihitung melalui `hash('sha256', $canonicalJsonString)`. Verifier `ConsolidatedTrialBalanceCertificateVerifier::verify($certificate)` memvalidasi signature tersebut.
