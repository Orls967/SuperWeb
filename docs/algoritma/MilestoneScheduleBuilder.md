# Algoritma H17: MilestoneScheduleBuilder

## 1. Definisi & Ruang Lingkup
Algoritma `MilestoneScheduleBuilder` menyusun jadwal termin pembayaran berjenjang (*staged payment milestones*) untuk kontrak jangka panjang MICE dan pernikahan (periode reservasi 3 hingga 12 bulan ke depan). Algoritma ini menjamin konservasi total nilai rupiah kontrak, mencegah selisih pembulatan desimal, dan menghitung tanggal jatuh tempo yang aman secara otomatis.

## 2. Struktur Data Masukan (Input)
- `contractId` (string): Nomor kontrak MICE atau pernikahan.
- `totalContractAmountIdr` (int): Total nilai kontrak yang disepakati pemesan.
- `signingDate` (string Y-m-d): Tanggal penandatanganan SPK.
- `eventStartDate` (string Y-m-d): Tanggal hari pertama pelaksanaan acara.
- `customMilestoneRatios` (array opsional): Bobot persentase termin (default: [0.20, 0.30, 0.30, 0.20]).

## 3. Logika Eksekusi & Invarian Konservasi
1. Validasi Rentang Waktu:
   - Hitung selisih hari $\Delta_{\text{days}} = \text{eventStartDate} - \text{signingDate}$.
   - Wajib $\Delta_{\text{days}} \ge 7$ hari.
2. Perhitungan Alokasi Rupiah:
   - Termin 1 (Deposit Konfirmasi 20%):
     $$M_1 = \lfloor 0.20 \times \text{totalContractAmountIdr} \rfloor$$
     Jatuh tempo: $\text{signingDate} + 7 \text{ hari}$ (atau $\min(\text{signingDate} + 7, \text{eventStartDate} - 1)$ jika sangat mepet).
   - Termin 2 (Termin Tengah 30%):
     $$M_2 = \lfloor 0.30 \times \text{totalContractAmountIdr} \rfloor$$
     Jatuh tempo: $\max(\text{signingDate} + 14 \text{ hari}, \text{eventStartDate} - 90 \text{ hari})$.
   - Termin 3 (Termin Jaminan Final 30%):
     $$M_3 = \lfloor 0.30 \times \text{totalContractAmountIdr} \rfloor$$
     Jatuh tempo: $\max(\text{signingDate} + 21 \text{ hari}, \text{eventStartDate} - 30 \text{ hari})$.
   - Termin 4 (Pelunasan Sisa):
     $$M_4 = \text{totalContractAmountIdr} - (M_1 + M_2 + M_3)$$
     Jatuh tempo: $\text{eventStartDate} - 7 \text{ hari}$.
3. Konservasi Nilai:
   - Karena $M_4$ dihitung sebagai selisih langsung, dipastikan $\sum_{k=1}^4 M_k \equiv \text{totalContractAmountIdr}$.
4. Penerbitan Sertifikat:
   - Algoritma memproduksi `MilestoneScheduleCertificate` yang memuat jadwal termin terurut, tanggal jatuh tempo, verifikasi jumlah total, dan signature SHA-256.

## 4. Struktur Output
```json
{
  "contract_id": "WED-2026-071",
  "total_contract_amount_idr": 250000000,
  "signing_date": "2026-11-08",
  "event_start_date": "2027-05-15",
  "milestones": [
    {
      "milestone_index": 1,
      "name": "Deposit Konfirmasi (DP)",
      "percentage": 20.0,
      "amount_idr": 50000000,
      "due_date": "2026-11-15"
    },
    {
      "milestone_index": 2,
      "name": "Termin I (Perencanaan & Vendor Lock)",
      "percentage": 30.0,
      "amount_idr": 75000000,
      "due_date": "2027-02-14"
    },
    {
      "milestone_index": 3,
      "name": "Termin II (Finalisasi Menu & Rooming List)",
      "percentage": 30.0,
      "amount_idr": 75000000,
      "due_date": "2027-04-15"
    },
    {
      "milestone_index": 4,
      "name": "Pelunasan Akhir (Final Balance)",
      "percentage": 20.0,
      "amount_idr": 50000000,
      "due_date": "2027-05-08"
    }
  ],
  "certificate_signature": "c8a1..."
}
```

