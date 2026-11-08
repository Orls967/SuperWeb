# Konsep Teknis & Domain Bisnis: Modul Aviation (Lini 26, Fase 177)

## 1. Ikhtisar & Latar Belakang

Modul `Aviation` (prefiks tabel `avi_*`) memodelkan fondasi industri transportasi udara komersial berjadwal (scheduled commercial aviation) di wilayah kepulauan Indonesia. Sektor penerbangan Indonesia diatur secara ketat oleh Kementerian Perhubungan Republik Indonesia melalui Peraturan Menteri Perhubungan Republik Indonesia (Permenhub), Undang-Undang Perlindungan Data Pribadi (UU PDP No. 27/2022), serta standardisasi internasional International Air Transport Association (IATA) dan International Civil Aviation Organization (ICAO).

Pada Fase 177, modul ini mengimplementasikan kemampuan vertikal inti (Aviation Core):
1. **Koordinasi Slot Musiman Bandara**: Alokasi kapasitas apron dan runway per jam mengacu pada IATA Worldwide Airport Slot Guidelines (WSG) dan Permenhub PM 89/2015 dengan aturan historis kakek (grandfather rights) 80/20 "use it or lose it".
2. **Manajemen Armada & Kelaikan Terbang (Airworthiness)**: Pelacakan registrasi pesawat (PK-***), batas bobot MTOW/OEW, batas jam terbang kumulatif, siklus pendaratan, batas interval perawatan A/B/C/D-Check, dan verifikasi sertifikat kelaikan udara sebelum rilis operasi.
3. **Pelepasan Penerbangan & Weight and Balance (W&B)**: Evaluasi gerbang kelaikan terkomputasi (Airworthiness Dispatch Gate) dan penghitungan amplop titik berat pusat gravitasi (Center of Gravity Envelope) guna menjamin keselamatan navigasi penerbangan.
4. **Inventaris Kursi & Reservasi Penumpang**: Manajemen inventaris kursi per kabin (Ekonomi Y dan Bisnis J), reservasi 2-langkah dengan batas waktu hold 15 menit (TTL lock), proteksi kelebihan pemesanan (oversell guard), enkripsi data pribadi penumpang (UU PDP), dan pembatalan serta refund tiket bergradien waktu (PM 89/2015).
5. **Kargo Udara & Dangerous Goods (DG)**: Validasi penerimaan konsinyasi kargo udara terhadap aturan pembatasan bahan berbahaya IATA Dangerous Goods Regulations (DGR) Kelas 1–9, batas kapasitas kargo sisa pesawat penumpang, dan pelacakan rantai kustodi hash-chain kanonikal.
6. **Buku Besar Terintegrasi (Double-Entry Ledger)**: Pengakuan pendapatan bertahap (unearned revenue saat reservasi terbayar menjadi earned passenger revenue saat pesawat lepas landas/departed), pencatatan biaya hanggar pemeliharaan, dan penyelesaian kewajiban pengembalian dana (refund payable).

---

## 2. Invarian Mutlak & Aturan Bisnis

Sistem menerapkan sekumpulan invarian mutlak yang tidak dapat dilanggar oleh kondisi apa pun (enforced at database, domain, and architecture layers):

### 2.1 Invarian Keuangan & Angka
1. **Zero Floating Point**: Seluruh nilai moneter disimpan dalam satuan minor (IDR dalam rupiah bulat/sen minor), rasio/persentase dalam basis poin integer (bps, 1% = 100 bps), dan bobot kargo dalam gram integer (`weight_g`) atau kilogram integer (`mtow_kg`, `oew_kg`). Penggunaan tipe data `float` atau `double` dilarang keras.
2. **Double-Entry Balance Invariant**: Setiap jurnal mutasi transaksi keuangan aviasi harus memiliki jumlah saldo debit dan kredit yang seimbang sempurna (`sum(amount) == 0`).
3. **Pendapatan Ditangguhkan (Unearned Revenue)**: Uang tiket yang dibayarkan penumpang tidak boleh langsung diakui sebagai laba/pendapatan operasional saat pemesanan, melainkan dicatat sebagai kewajiban lancar (unearned revenue) hingga penerbangan resmi lepas landas (`status = departed`).

### 2.2 Invarian Operasional & Keselamatan
1. **Airworthiness Dispatch Gate (A2)**: Pesawat dilarang keras dilepas (`dispatch release`) apabila:
   - Status pesawat bukan `active` (misalnya `maintenance`, `grounded`, atau `scrapped`).
   - Masa berlaku sertifikat kelaikan udara (`airworthiness_expires_at`) telah kedaluwarsa atau kurang dari 24 jam sebelum jadwal tiba.
   - Jam terbang operasional melampaui batas toleransi A-Check tanpa persetujuan penangguhan resmi (MEL Deferral).
2. **Center of Gravity Envelope (A3)**: Titik berat pusat gravitasi akhir pesawat (`cg_mm`) setelah menggabungkan berat pesawat kosong (OEW), bahan bakar lepas landas, awak kabin, penumpang, dan kargo harus berada di dalam batas toleransi aman:
   $$\text{cg\_forward\_limit\_mm} \le \text{cg\_mm} \le \text{cg\_aft\_limit\_mm}$$
   Jika $cg$ berada di luar rentang ini, penerbangan mengalami risiko stall atau kegagalan kendali elevator dan wajib ditolak untuk dispatch.
3. **Alokasi Slot 80/20 IATA (A1)**: Operator berjadwal berhak mempertahankan hak historis atas slot bandara pada musim berikutnya (W27/S28) apabila tingkat realisasi penggunaan slot historis pada musim berjalan minimal mencapai 80% ($8.000\text{ bps}$). Jika utilisasi $< 80\%$, hak historis dicabut dan slot dikembalikan ke pool alokasi umum.
4. **Dangerous Goods Rejection (A5)**:
   - Kargo berbahaya Kelas 1 (Bahan Peledak / Explosives) dan Kelas 7 (Radioaktif) dilarang dimuat dalam penerbangan berpenumpang komersial (Pax flight).
   - Bahan berbahaya Kelas 9 (misalnya baterai lithium ion UN3480) dibatasi maksimal $35\text{ kg}$ per paket kemasan pada pesawat berpenumpang.
5. **Kebijakan Refund Tiket Permenhub PM 89/2015 (A4)**:
   - Pengembalian dana akibat pembatalan oleh maskapai (IRROPS, erupsi, atau kendala teknis) wajib dikembalikan $100\%$ tanpa potongan biaya administrasi apa pun.
   - Pembatalan atas kehendak penumpang dikenakan potongan bergradien:
     - $> 72\text{ jam}$ sebelum berangkat: Pengembalian $75\%$ dari tarif dasar + $100\%$ pajak bandara/PPN.
     - $48\text{ s.d. } 72\text{ jam}$ sebelum berangkat: Pengembalian $50\%$ dari tarif dasar + $100\%$ pajak.
     - $24\text{ s.d. } 48\text{ jam}$ sebelum berangkat: Pengembalian $40\%$ dari tarif dasar + $100\%$ pajak.
     - $12\text{ s.d. } 24\text{ jam}$ sebelum berangkat: Pengembalian $30\%$ dari tarif dasar + $100\%$ pajak.
     - $4\text{ s.d. } 12\text{ jam}$ sebelum berangkat: Pengembalian $20\%$ dari tarif dasar + $100\%$ pajak.
     - $< 4\text{ jam}$ / No-Show: Pengembalian $10\%$ dari tarif dasar + $100\%$ pajak.
     - Biaya administrasi maksimum dipatok $10\%$ dari tarif dasar atau maksimal Rp 150.000 per penumpang.

---

## 3. Arsitektur Domain & Siklus Hidup Entitas

### 3.1 State Machine Penerbangan (Flight State Machine)
Siklus operasional penerbangan komersial melewati 8 status deterministik:
```
[ planned ] 
    │
    ▼ (alokasi slot bandara terkonfirmasi)
[ slot_secured ]
    │
    ▼ (pemeriksaan kelaikan A2 & W&B A3 berhasil)
[ airworthy_cleared ]
    │
    ├─────────────────────────────┐
    ▼ (boarding penumpang)        ▼ (direct off-block ferry/cargo)
[ boarding ] ────────────────► [ departed ] (pengakuan revenue ke ledger)
                                  │
                                  ▼ (roda menyentuh runway tujuan)
                               [ arrived ]
                                  │
                                  ▼ (penyelesaian manifest kargo & bagasi)
                               [ closed ]
```
Pada setiap status sebelum `departed`, penerbangan dapat bertransisi ke status `cancelled` bila terjadi force majeure (erupsi gunung berapi, cuaca ekstrem, penutupan bandara NOTAM).

### 3.2 State Machine Pemesanan Tiket (Ticket State Machine)
```
[ held ] (inventaris terkunci 15 menit)
    │
    ├───► [ expired ] (timeout tanpa pembayaran)
    │
    ▼ (pembayaran diverifikasi gateway)
[ issued ] (boarding pass diterbitkan, PII dienkripsi)
    │
    ├───► [ checked_in ] ──► [ boarded ] ──► [ flown ]
    │
    ▼ (penumpang mengajukan pengembalian dana)
[ refund_pending ] ──► [ refunded ] (posting ledger refund payable)
```

### 3.3 State Machine Kargo Udara (Cargo Consignment State Machine)
```
[ booked ] 
    │
    ▼ (inspeksi fisik & lolos DGR A5)
[ accepted ] (rantai kustodi hash diinisialisasi)
    │
    ▼ (penimbangan & masuk palet ULD)
[ screened ]
    │
    ▼ (dimuat ke kargo hold pesawat)
[ manifested ] ──► [ in_transit ] ──► [ delivered ]
    │
    ▼ (pelanggaran kemasan / kebocoran zat kimia)
[ rejected ]
```

---

## 4. Perlindungan Data Pribadi (UU PDP No. 27/2022)

Sesuai Undang-Undang Perlindungan Data Pribadi Republik Indonesia, data identitas penumpang (nama lengkap, NIK/nomor paspor, tanggal lahir) diperlakukan sebagai Data Pribadi Sensitif.
- **Penyimpanan Terenkripsi**: Kolom `pii_name_encrypted` disimpan menggunakan algoritma enkripsi autentikasi simetris AES-256-GCM melalui Laravel Encryption Service. Data dalam bentuk teks polos (plaintext) tidak pernah disimpan di disk atau database.
- **Pencarian Tanpa Dekripsi (Blind Indexing)**: Kolom `pii_blind_index` dihitung menggunakan HMAC-SHA256 dengan kunci rahasia (*pepper*) yang dirotasi secara berkala:
  $$\text{pii\_blind\_index} = \text{HMAC-SHA256}(\text{PEPPER}, \text{strtoupper}(\text{trim}(\text{name})))$$
- **Pencegahan Akses Tak Sah (Anti-IDOR Policy)**: Akses ke portal tiket penumpang `/passenger/ticket/{ticket}` dilindungi oleh `AviationTicketPolicy`. Pengguna dengan peran penumpang hanya diizinkan membuka tiket yang memiliki `passenger_party_id` cocok dengan identitas akun yang sedang terotentikasi. Permintaan tiket orang lain langsung ditolak dengan kode status HTTP 403 Forbidden.

---

## 5. Integrasi Buku Besar (Double-Entry Ledger)

Modul Aviation terhubung ke sistem Buku Besar Terpadu (Unified Banking Ledger) melalui akun-akun standar yang terdaftar di `AviationAccounts`:
1. `avi:unearned_revenue:IDR` (Kewajiban / Pendapatan Diterima di Muka)
2. `avi:ticket_revenue:IDR` (Pendapatan Operasional Penerbangan)
3. `avi:ancillary_revenue:IDR` (Pendapatan Tambahan Bagasi / Kursi Pilihan)
4. `avi:cargo_revenue:IDR` (Pendapatan Angkutan Kargo Udara)
5. `avi:refund_payable:IDR` (Kewajiban Pengembalian Dana Tiket)
6. `avi:refund_penalty_income:IDR` (Pendapatan Biaya Administrasi Pembatalan)
7. `avi:fuel_clearing:IDR` (Kliring Biaya Avtur Bandara)
8. `avi:maintenance_expense:IDR` (Beban Pemeliharaan Pesawat Hanggar MRO)

Setiap entri transaksi dicatat secara idempoten menggunakan format kunci idempotensi deterministik:
`avi:rev:flight:{flight_id}:{timestamp}` untuk pengakuan pendapatan terbang, dan
`avi:refund:{ticket_id}:{timestamp}` untuk penyelesaian klaim refund.

---

## 6. Penanganan Faktor Eksternal & Kejutan Deterministik (T-5, ADR-0051)

Kinerja operasional dan finansial maskapai sangat peka terhadap perubahan variabel eksternal:
1. **Fluktuasi Harga Avtur Jet A-1**: Harga avtur dipantau melalui tabel `avi_external_factors` dengan kunci `avtur_price_cgk_minor`. Setiap kenaikan harga bahan bakar di atas batas toleransi memicu penyesuaian perhitungan biaya langsung per jam terbang (DOC).
2. **Kejutan Erupsi Gunung Berapi (Ash Cloud Impact)**: Indonesia berada di jalur Cincin Api Pasifik (Ring of Fire). Erupsi gunung berapi (seperti Gunung Agung di Bali, Sinabung di Sumatera, atau Semeru di Jawa) menyebabkan bahaya partikel kaca silika yang merusak turbin jet pesawat.
   - Skenario Seeder T1: Penutupan Bandara I Gusti Ngurah Rai (DPS) selama 3 hari akibat abu vulkanik VONA (`eruption_ash_cloud`, impact: `ground_stop`).
   - Tindakan Sistem: Seluruh penerbangan dari/menuju DPS dalam rentang tanggal terdampak secara otomatis dialihkan ke status `cancelled`.
   - Hak Penumpang: Penumpang mendapatkan opsi pembatalan dengan pengembalian dana $100\%$ tanpa potongan (PM 89/2015) yang dieksekusi melalui `FlightCancellationMassRefundSaga`.

---

## 7. Katalog Kasus Pengujian & Simulasi (15 Kasus Unik)

Berikut 15 kasus operasional yang dicakup dan diuji di modul Aviation:

| ID Kasus | Nama Skenario | Deskripsi & Prasyarat | Ekspektasi Sistem |
| :--- | :--- | :--- | :--- |
| **CAS-AVI-01** | *Alokasi Slot Musiman Historic* | Operator GA mengajukan slot W26 di CGK jam 08:00 UTC dengan histori utilisasi 88%. | Status `allocated`, prioritas `historic` diberikan secara otomatis (A1). |
| **CAS-AVI-02** | *Penolakan Slot Kakek Bawah 80%* | Operator mengajukan slot dengan tingkat utilisasi musim lalu 74% (di bawah 80%). | Hak historis dicabut, dialihkan ke antrean pool umum (A1). |
| **CAS-AVI-03** | *Pesawat Kedaluwarsa Kelaikan* | Pesawat PK-SJA memiliki sertifikat kelaikan udara yang kedaluwarsa 2 hari lalu. | Dispatch ditolak dengan galat `AIRWORTHINESS_EXPIRED` (A2). |
| **CAS-AVI-04** | *Pesawat Masuk Hanggar Perawatan* | Pesawat PK-GAA berstatus `maintenance` (A-Check) dijadwalkan terbang. | Dispatch diblokir; pesawat berstatus non-aktif tidak boleh dirilis (A2). |
| **CAS-AVI-05** | *Jam Terbang Lampaui Interval A-Check* | Jam terbang berjalan mencapai 37.200 menit, batas A-Check 36.000 menit tanpa deferral. | Dispatch ditolak; peringatan wajib inspeksi hanggar (A2). |
| **CAS-AVI-06** | *Titik Berat Forward Limit Exceeded* | Muatan kargo depan terlalu berat sehingga CG berada di 8.400 mm (batas depan 8.600 mm). | W&B checker menolak sertifikasi; hidung pesawat berisiko menukik (A3). |
| **CAS-AVI-07** | *Titik Berat Aft Limit Exceeded* | Muatan penumpang terkonsentrasi di kabin belakang sehingga CG berada di 9.800 mm (batas belakang 9.600 mm). | W&B checker menolak sertifikasi; ekor berisiko terbentur landasan (A3). |
| **CAS-AVI-08** | *Kargo DG Kelas 1 di Pesawat Pax* | Konsinyasi amunisi militer (Kelas 1) diajukan pada penerbangan komersial GA-404. | Ditolak seketika; bahan peledak dilarang pada pesawat penumpang (A5). |
| **CAS-AVI-09** | *Baterai Lithium Lampaui Batas Paket* | Paket baterai lithium ion UN3480 berbobot 60 kg diajukan untuk penerbangan pax. | Ditolak; batas aman pax flight maksimal 35 kg per kemasan (A5). |
| **CAS-AVI-10** | *Hold Kursi & Kedaluwarsa 15 Menit* | Kursi di-hold tanpa pembayaran dalam jendela waktu 15 menit. | Kursi otomatis dikembalikan ke inventaris bebas setelah TTL lewat. |
| **CAS-AVI-11** | *Pencegahan Overselling Kursi* | Permintaan hold kursi melebihi kapasitas kabin Y yang tersisa. | Ditolak dengan `CapacityExceededException`; kursi tidak boleh minus. |
| **CAS-AVI-12** | *Refund Voluntary > 72 Jam (PM 89)* | Penumpang membatalkan tiket 5 hari sebelum keberangkatan atas keinginan sendiri. | Refund 75% tarif dasar + 100% pajak; fee administrasi dipotong (A4). |
| **CAS-AVI-13** | *Refund Involuntary Erupsi Gunung* | Penerbangan dibatalkan akibat abu vulkanik penutupan bandara DPS. | Refund 100% penuh tarif dasar dan pajak tanpa potongan biaya (A4). |
| **CAS-AVI-14** | *Pengakuan Pendapatan Lepas Landas* | Pesawat GA-404 berpindah status dari `boarding` ke `departed`. | Unearned revenue didebit, ticket revenue dikredit di buku besar. |
| **CAS-AVI-15** | *Proteksi IDOR Portal Penumpang* | Pengguna Pax2 mencoba mengakses URL `/passenger/ticket/{pax1_ticket_id}`. | Ditolak dengan kode status HTTP 403 Forbidden; log peringatan dicatat. |

---

## 8. Standar Kompensasi Keterlambatan Penerbangan (Permenhub PM 89/2015)

Kepatuhan terhadap Peraturan Menteri Perhubungan No. PM 89 Tahun 2015 tentang Penanganan Keterlambatan Penerbangan (Delay Management) diatur dengan matriks kompensasi otomatis:

| Kategori Keterlambatan | Durasi Keterlambatan | Bentuk Kompensasi Wajib | Penanganan Sistem Modul Aviation |
|---|---|---|---|
| **Kategori 1** | 30 s.d. 60 menit | Minuman ringan (*refreshment* / snack kecil). | Notifikasi otomatis SMS/email ke penumpang. |
| **Kategori 2** | 61 s.d. 120 menit | Minuman dan makanan ringan (*heavy snack*). | Penerbitan voucer konsumsi digital bandara. |
| **Kategori 3** | 121 s.d. 180 menit | Minuman dan makanan berat (*heavy meal*). | Penerbitan voucer makan restoran mitra bandara. |
| **Kategori 4** | 181 s.d. 240 menit | Makanan dan minuman berat + alternatif penerbangan. | Pembukaan alokasi kursi penerbangan berikutnya tanpa biaya tambahan. |
| **Kategori 5** | > 240 menit | Ganti rugi uang tunai Rp 300.000 per penumpang. | Pembukuan klaim otomatis pada akun kewajiban kompensasi delay maskapai. |
| **Kategori 6** | Pembatalan Penerbangan (*Cancelled*) | Pengembalian seluruh biaya tiket (100% refund) ATAU pengalihan penerbangan + akomodasi hotel. | Integrasi ke `HotelIrropsAccommodationService` dan `FlightCancellationMassRefundSaga`. |

---

## 9. Kode Keterlambatan Standar IATA (IATA Delay Codes AHM 730)

Pencatatan penyebab keterlambatan operasional penerbangan pada tabel `avi_flights` mengacu pada standar global IATA Airport Handling Manual (AHM 730):

- **Kode 11-19 (Penumpang & Bagasi):** Keterlambatan check-in, penumpang terlambat masuk pintu (*gate no-show*), rekonsiliasi bagasi kabin.
- **Kode 21-29 (Kargo & Pos):** Keterlambatan dokumentasi AWB kargo, inspeksi DGR ulang, keterlambatan pemuatan kontainer ULD.
- **Kode 31-39 (Peralatan Darat / Ramp):** Kerusakan tangga belalai garbarata (*aerobridge*), keterlambatan pushback tug, keterlambatan pengisian bahan bakar (*fueling*).
- **Kode 41-49 (Teknis & Perawatan):** Perbaikan kerusakan tak terjadwal (*unscheduled maintenance*), verifikasi item kelaikan MEL kategori B/C.
- **Kode 51-59 (Kerusakan Pesawat Akibat Faktor Luar):** Sambaran petir (*lightning strike*), tabrakan burung (*bird strike*), kerusakan saat penarikan di apron.
- **Kode 71-79 (Cuaca / Meteorologi):** Kabut tebal di bawah batas jarak pandang (*below CAT II/III minimums*), badai petir, penutupan bandara akibat sebaran abu vulkanik (*VONA Alert RED*).
- **Kode 81-89 (Navigasi Udara & Kontrol Bandara / ATC):** Pembatasan slot ruang udara (*en-route ATC slot restriction*), antrean lepas landas landas pacu sibuk.

---

## 10. Kerangka Kerja Pengurangan Emisi Karbon & CORSIA

Modul Aviasi mendukung perhitungan emisi karbon penerbangan sesuai skema ICAO CORSIA (*Carbon Offsetting and Reduction Scheme for International Aviation*):
- Konsumsi bahan bakar avtur per penerbangan dicatat dalam kilogram (`fuel_burn_kg`).
- Faktor konversi emisi standar ICAO: 1 kg avtur Jet A-1 menghasilkan 3,16 kg $CO_2$ ekuivalen.
- Integrasi ke modul ESG memungkinkan pelaporan jejak emisi per penumpang/kilometer dan kompensasi otomatis melalui pembelian kredit karbon (*carbon credit retirement*).
- Penghitungan emisi per kabin memperhitungkan rasio densitas kursi (faktor pembobot kabin Bisnis 2x terhadap kabin Ekonomi).
- Data agregat emisi triwulanan diverifikasi sebelum diserahkan ke Direktorat Kelaikudaraan dan Pengoperasian Pesawat Udara (DKPPU).

---

## 11. Arsitektur Integrasi Intermoda (Konektivitas Darat, Kereta, Laut, dan Udara)

Dalam ekosistem SuperWeb, modul Aviasi (Lini 26) dirancang untuk beroperasi secara mulus dengan pilar transportasi lainnya:
- **Konektivitas Kereta Bandara (Airport Rail Link):** Penumpang dapat memesan tiket terusan kereta bandara yang diterbitkan secara *single-ticket* melalui integrasi modul Transit.
- **Intermoda Maritim & Ferry:** Untuk kepulauan terpencil yang tidak terjangkau pesawat komersial besar, rute perintis dikombinasikan dengan tiket kapal cepat ferry di modul Marine (Lini 27).
- **Penanganan Bagasi Terpadu (Through-Check Baggage):** Pengalihan bagasi otomatis antar maskapai mitra (*interline agreement*) dengan pemantauan kode batang IATA Barcode dan RFID.





