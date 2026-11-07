# ADR-0089: Ticketing Hash-Chain, Access Control Anti-Passback, Usia & Operasional Venue

## Status
Diterima (Accepted) — 2026-11-07

## Konteks Bisnis & Masalah
Operasional venue hiburan berskala besar (beach club, kelab malam, festival pesisir) menghadapi tantangan keselamatan kerumunan (*crowd crush*), kebocoran pendapatan akibat pemalsuan atau *ticket replay*, kepatuhan batas usia minimum legal (UU PDP & peraturan perizinan daerah), serta risiko pembatalan akibat cuaca pesisir ekstrem. Diperlukan arsitektur tiket digital *non-fungible* dengan rantai hash (*hash-chain*), kendali akses gerbang dua arah dengan perlindungan *anti-passback* dan *anti-replay*, pembukuan berbasis akrual (*unearned revenue* hingga acara berstatus *closed*), serta *saga* kompensasi penanganan pembatalan terdistribusi.

## Keputusan Arsitektur

### 1. Satuan Nilai Moneter & Kuantitas (G7-D32)
- Mata uang IDR disimpan dalam integer rupiah penuh; USD/SGD dalam satuan minor integer (sen).
- Persentase disimpan dalam basis poin (bps) integer.
- Kepadatan zona dinyatakan dalam $\text{orang/m}^2 \times 100$ integer.
- Pembagian proporsional menggunakan Hamilton Largest Remainder dengan tie-break deterministik. Float dilarang untuk uang dan kuantitas.

### 2. Pengakuan Pendapatan Berbasis Akrual (G7-D33)
- Seluruh penjualan tiket, paket VIP, dan reservasi meja mula-mula dibukukan ke akun kewajiban pendapatan diterima di muka (`ven:ticket_unearned:IDR` dan `ven:vip_unearned_rev:IDR`).
- Pendapatan definitif baru diakui ke `ven:ticket_revenue:IDR` saat acara ditutup secara formal (`CloseEntertainmentEventAction`).
- Pajak Barang dan Jasa Tertentu (PBJT) sebesar 40% (kelab malam) atau 10% (konser) dicatat ke `ven:entertainment_tax_payable:IDR` saat transaksi kas diterima.

### 3. Tiket Digital Gapless & Hash-Chain Non-Fungible (G7-D34, D89-2)
- Setiap tiket diterbitkan dengan nomor urut tanpa jeda (*gapless*) per event via `DocumentNumberingInterface` dan ditautkan ke rantai hash kanonik `CanonicalHashChain`.
- Tiket hanya dapat ditransfer satu kali; transfer menghasilkan tiket baru dengan status tiket lama menjadi `transferred`.
- Setiap pemindaian membawa *nonce* unik sekali pakai; pemindaian ulang dengan *nonce* yang sama ditolak seketika (*anti-replay*).

### 4. Perlindungan Data Pribadi & Verifikasi Usia (G7-D35, T-8)
- Pemeriksaan kelayakan usia pengunjung ($\ge 21$ tahun untuk kelab malam atau $\ge 18$ tahun untuk konser umum) dilakukan melalui kontrak adaptor `PartyAgeVerificationInterface`.
- Sistem tidak pernah menyimpan atau menampilkan tanggal lahir mentah (PII) di layar pemindaian gerbang petugas (375 px).

### 5. Algoritma Bersertifikat Operasional Venue (T-4)
- **V1 `TierCountdownPricer`**: Menghitung harga dinamis berbasis elastisitas countdown tier, rasio kapasitas terjual, dan faktor cuaca pesisir yang dijepit di batas $[floor, ceiling]$.
- **V2 `AntiPassbackGate`**: Menegakkan status pindaian bergantian MASUK/KELUAR dengan batas toleransi *re-entry* $N=2$.
- **V3 `ZoneCapacityGovernor`**: Mengatur admisi zona berbasis *token bucket* laju masuk per menit dan *safety margin* kapasitas legal ($5\%$).
- **V4 `MinimumSpendSettlement`**: Menghitung komitmen *minimum spend* meja VIP, *shortfall revenue*, serta pemotongan deposit *no-show* ($50\%$).

### 6. State Machine Eksplisit (D89-1, D89-2, D89-4)
- `EntertainmentEvent`: 8 status (`draft` $\to$ `permit_pending` $\to$ `on_sale` $\to$ `sold_out` $\to$ `live` $\to$ `closed` $\to$ `settled` $\to$ `cancelled`).
- `EntertainmentTicket`: 8 status (`reserved` $\to$ `issued` $\to$ `transferred` $\to$ `scanned_in` $\to$ `scanned_out` $\to$ `used` $\to$ `refunded` $\to$ `void`).
- `EntertainmentTableBooking`: 7 status (`requested` $\to$ `deposit_held` $\to$ `confirmed` $\to$ `seated` $\to$ `settled` $\to$ `no_show_forfeited` $\to$ `cancelled_refunded`).

### 7. Integrasi Kontrak Lintas Modul & Saga Kompensasi (D89-5, D89-6)
- **G7-K15 `EventCoverBinder`**: Penerbitan polis asuransi pembatalan acara di modul Insurance via `IssueEmbeddedPolicyAction` dan pengajuan klaim saat *force majeure*.
- **G7-K17 `OutletSaleRecorder`**: Pencatatan tagihan pesanan makanan/minuman dari modul Resto langsung ke nomor meja venue VIP.
- **Saga Pembatalan Acara**: Membatalkan tiket, membalik kewajiban pendapatan tiket dan PBJT, mengembalikan deposit meja VIP, serta mencatat klaim asuransi kerugian acara.

## Konsekuensi
- **Positif:** Mengeliminasi celah pemalsuan tiket dan *passback*, melindungi keselamatan pengunjung melalui *safety margin* otomatis, serta menjamin kepatuhan akuntansi *double-entry* IFRS 15.
- **Mitigasi:** Kompleksitas alur *state machine* dan *saga* dikawal dengan pengujian akseptansi *end-to-end*, audit dua sumber `ven:audit`, dan verifikasi sertifikat matematis pada setiap eksekusi algoritma.
