# Spesifikasi & Pembuktian Matematis: FolioSplitRouter (H6)

## 1. Definisi Masalah
Dalam akuntansi perhotelan korporat, pemisahan pos biaya (*folio splitting*) antara penanggung jawab perusahaan (*Corporate Payer*) dan tamu perorangan (*Personal Payer*) harus memenuhi prinsip konservasi nilai:
$$\sum_{i=1}^{n} c_i = C_{corporate} + C_{personal}$$
di mana:
- $c_i \ge 0$ adalah nilai moneter pada baris transaksi folio ke-$i$.
- $C_{corporate}$ adalah total alokasi tagihan ke piutang korporat (*City Ledger AR*).
- $C_{personal}$ adalah total alokasi tagihan ke kasir tamu personal.

## 2. Aturan Perutean Deterministik
1. **Eligibilitas Kategori**:
   Pos $c_i$ memenuhi syarat penagihan korporat jika dan hanya jika:
   $$Category(c_i) \in \{\text{ROOM\_CHARGE}, \text{HOTEL\_TAX}, \text{SERVICE\_CHARGE}, \text{BUSINESS\_INTERNET}, \text{AIRPORT\_TRANSFER}\}$$
   dan persetujuan korporat aktif ($BillingEnabled = \text{true}$).
2. **Pagu Kredit Korporat (Credit Limit Guardrail)**:
   $$C_{corporate} \le L_{credit}$$
   Jika alokasi $c_i$ menyebabkan total penggunaan melampaui $L_{credit}$, maka pos tersebut dialihkan ke sub-folio Personal untuk mencegah gagal bayar korporat.

## 3. Invarian dan Teorema Verifikasi
- **Teorema Konservasi**: Tidak ada rupiah yang hilang atau tercipta selama proses routing.
- **Teorema Non-Negativitas**: $C_{corporate} \ge 0$ dan $C_{personal} \ge 0$.
- **Sertifikasi Hash-Chain**: Integritas hasil alokasi diikat oleh SHA-256 checksum:
  $$Checksum = \mathcal{H}(FolioId \parallel Total \parallel C_{corp} \parallel C_{pers} \parallel Count)$$

