# KONSEP FASE 180 — LINI 27: ARMADA KAPAL, SHIP MANAGEMENT, CHARTER & MARINE SERVICES

## 1. Eksekutif & Latar Belakang Domain Maritim

Fase 180 mengimplementasikan pilar operasional maritim komprehensif pada Modul **Port** (`prt_`) untuk Lini 27. Di sektor logistik samudra modern, manajemen armada kapal (*Ship Management & Ocean Fleet Operations*) bukan sekadar pencatatan posisi kapal, melainkan agregasi kompleks yang menghubungkan kelaiklautan teknis (*statutory classification & SOLAS/STCW compliance*), efisiensi hidrodinamika & bahan bakar (*propulsion cubic law & IMO CII compliance*), hukum perdagangan maritim komersial (*BIMCO Charter Party & Laytime Settlement*), penanganan kedaruratan laut (*York-Antwerp Rules 2016 General Average*), serta akuntansi keuangan ganda (*double-entry fleet ledger PDA/FDA*).

Implementasi Fase 180 menggantikan kode kerangka lama `Modules\Integration\Application\Services\OceanFleetService` menjadi *vertical slice* enterprise lengkap tanpa meninggalkan hutang teknis ataupun jalan pintas.

---

## 2. Arsitektur Domain & Hubungan Antarmodul

```
                                +-----------------------------------+
                                |    Logistics (Master Kapal)       |
                                +-----------------+-----------------+
                                                  | Kueri Kapal
                                                  v
+-----------------------+       +-----------------------------------+       +-----------------------+
|  Insurance Modul      |<----->|     Modules\Port (Armada Kapal)   |<----->|  Asset Maintenance    |
| (Klaim H&M, P&I, War) |       |  - prt_fleet_vessels              |       | (Work Order Drydock)  |
+-----------------------+       |  - prt_fleet_voyages              |       +-----------------------+
                                |  - prt_fleet_charter_parties      |
+-----------------------+       |  - prt_fleet_port_calls           |       +-----------------------+
|  ESG Modul            |<----->|  - prt_fleet_ledger_entries       |<----->|  PortOps Terminal     |
| (IMO CII MEPC.354(78))|       +-----------------+-----------------+       | (Window Dermaga K27)  |
+-----------------------+                         |                         +-----------------------+
                                                  |
                                                  v
                                +-----------------------------------+
                                |   Audit Command (prt:fleet-audit) |
                                |   & Verifikasi SHA-256 Ledger     |
                                +-----------------------------------+
```

### 2.1 Partisi Tanggung Jawab (Separation of Concerns)
1. **Modules\Port\Domain\Models\Fleet***: Entitas data dengan awalan tabel `prt_fleet_*` yang terisolasi secara relasional namun merujuk master IMO kapal logistik.
2. **Modules\Port\Domain\Algorithms\***: Mesin matematis deterministik bersertifikat tanpa ketergantungan framework:
   - `BunkerFuelConsumptionOptimizer` (A10): Model propulsi eksponensial kubik, penalti cuaca skala Beaufort, dan optimasi bunkering multi-pelabuhan.
   - `LaytimeDemurrageCalculator` (A11): Kalkulasi laytime BIMCO Gencon, SHINC/SHEX, pengecualian cuaca hujan, dan turn-time Notice of Readiness (NOR).
   - `CiiCarbonIntensityRatingEngine` (A12): Penghitungan intensitas emisi karbon operasional tahunan IMO MEPC.354(78) dan peringkat CII grade A hingga E.
   - `TimeCharterEquivalentCalculator` (A13): Metrik pendapatan harian bersih Baltic Exchange TCE terhadap ambang impas OPEX kapal.
   - `GeneralAverageContributionDistributor` (A14): Pembagian kontribusi kerugian laut darurat York-Antwerp Rules 2016 antara nilai kontributif kapal, kargo, dan uang tambang.
3. **Modules\Port\Domain\StateMachines\***:
   - `VoyageStateMachine`: Mengontrol siklus hidup pelayaran maritim 12 status (`PLANNED` -> `CHARTERED` -> `BUNKERING` -> `LOADING` -> `DEPARTED` -> `SEA_PASSAGE` -> `WEATHER_DEVIATION` -> `DISTRESS` -> `ARRIVED` -> `DISCHARGING` -> `COMPLETED` / `CANCELLED`).
   - `CharterPartyStateMachine`: Mengatur perikatan komersial sewa kapal 8 status (`NEGOTIATING` -> `FIXTURE_CONFIRMED` -> `ON_HIRE` -> `SUSPENDED_OFF_HIRE` -> `REDELIVERED` -> `SETTLED` / `DISPUTED` / `DEFAULTED`).
4. **Modules\Port\Application\Sagas\VoyageCharterExecutionSaga**: Orkestrasi kompensasi otomatis:
   - Langkah 1: Validasi sertifikat statutori kelaiklautan kapal (SOLAS, STCW, DOC, SMC).
   - Langkah 2: Pembuatan kontrak sewa kapal (*Charter Party*).
   - Langkah 3: Penjadwalan pelayaran voyage.
   - Langkah 4: Reservasi jendela tambatan dermaga via kontrak `G7-K27` (`VesselCallWindowProvider`).
   - Langkah 5: Alokasi anggaran PDA (*Proforma Disbursement Account*) pada buku besar maritim.
   - Langkah 6: Komitmen konfirmasi *fixture*. Jika terjadi kegagalan pada langkah mana pun, kompensasi otomatis membatalkan alokasi dermaga dan membalikkan jurnal ledger.

---

## 3. Spesifikasi Matematika & Algoritma Bersertifikat

### 3.1 A10: Optimasi Bahan Bakar & Konsumsi Propulsi (BunkerFuelConsumptionOptimizer)
Berdasarkan hidrodinamika perkapalan (*Admiralty Coefficient Law*), konsumsi bahan bakar propulsi harian ($F_d$ dalam metrik ton/hari) berbanding lurus dengan pangkat tiga kecepatan jelajah kapal ($V$ dalam knot) serta rasio perpindahan massa kapal terhadap kondisi sarat desain:
$$F_d = F_{\text{base}} \times \left( \frac{\Delta_{\text{actual}}}{\Delta_{\text{design}}} \right)^{2/3} \times \left( \frac{V_{\text{actual}}}{V_{\text{design}}} \right)^3 + F_{\text{aux}}$$
Di mana:
- $F_{\text{base}}$: Konsumsi harian mesin induk pada kecepatan desain pada sarat penuh (*design displacement* $\Delta_{\text{design}}$).
- $F_{\text{aux}}$: Konsumsi generator pembangkit listrik kapal dan boiler bantu harian.
- Penalti Cuaca (*Weather Margin Penalty*): Untuk kondisi laut di atas Skala Beaufort 4 ($BN > 4$), resistensi gelombang dan angin dihitung dengan faktor koreksi:
$$F_{\text{penalized}} = F_d \times \left(1 + \frac{(BN - 4) \times 0.045}{\text{faktor efisiensi lambung}}\right)$$
Multi-Port Bunkering Optimizer mengevaluasi trade-off ekonomi antara membeli bunker di pelabuhan muat, bunker di pelabuhan bongkar, atau melakukan deviasi bunker khusus berdasarkan perbandingan harga per metrik ton, biaya deviasi sewa kapal harian (OPEX/hire), biaya pelabuhan, dan resiko kehilangan slot dermaga.

### 3.2 A11: Kalkulasi Laytime & Demurrage Maritim (LaytimeDemurrageCalculator)
Mengikuti standar perkapalan *BIMCO Standard Laytime Definitions*:
1. **Notice of Readiness (NOR)**: Diserahkan saat kapal tiba di area labuh jangkar atau dermaga. Masa tunggu turn-time (umumnya 6 jam atau 12 jam) diabaikan dari perhitungan waktu laytime yang berjalan (*laytime commencement*).
2. **Klausula Operasional**:
   - `SHINC` (*Sundays and Holidays Included*): Hari Minggu dan libur resmi tetap dihitung sebagai waktu muat/bongkar yang berjalan.
   - `SHEX` (*Sundays and Holidays Excluded*): Periode libur dan akhir pekan dikecualikan kecuali jika kapal bekerja (*unless used*).
   - `WWD` (*Weather Working Day*): Waktu terganggu akibat cuaca buruk atau hujan lebat yang dibuktikan pada *Statement of Facts* (SOF) tidak dimasukkan ke dalam pemakaian laytime (*laytime exception*).
3. **Prinsip "Once on Demurrage, Always on Demurrage"**:
   Begitu waktu laytime yang diizinkan telah habis terlampaui, kapal masuk dalam kondisi demurrage. Sejak saat itu, seluruh waktu kalender (termasuk hari Minggu, hari libur, dan jeda hujan) dihitung sebagai demurrage penuh tanpa pengecualian hingga selesainya pelepasan selang kargo atau palka ditutup.
4. **Despatch Money**:
   Jika penyewa menyelesaikan pemuatan/pembongkaran lebih cepat daripada laytime yang diizinkan, pemilik kapal membayar ganti rugi waktu luang (*despatch*) yang biasanya disepakati sebesar $50\%$ dari tarif harian demurrage ($Tarif_{\text{despatch}} = 0.5 \times Tarif_{\text{demurrage}}$).

### 3.3 A12: Penilaian Peringkat Karbon IMO CII (CiiCarbonIntensityRatingEngine)
Sesuai resolusi IMO MEPC.354(78) dan MARPOL Annex VI Regulasi 28:
1. **Attained CII Operasional**:
$$\text{Attained CII} = \frac{\sum (FC_j \times C_{F,j})}{\text{DWT} \times D} \times 10^6 \quad [\text{gram } \text{CO}_2 / \text{DWT-nautical mile}]$$
Di mana $FC_j$ adalah konsumsi bahan bakar tipe $j$ (VLSFO, LSMGO, HFO, Biofuel, LNG), $C_{F,j}$ adalah faktor konversi emisi karbon bahan bakar sesuai pedoman IMO (VLSFO: 3.151, LSMGO: 3.206, MDO: 3.206, LNG: 2.750), $\text{DWT}$ adalah kapasitas bobot mati kapal, dan $D$ adalah total jarak pelayaran yang ditempuh dalam mil laut.
2. **Required CII Tahunan**:
$$\text{Required CII} = \text{Reference CII} \times \left(1 - \frac{Z}{100}\right)$$
Di mana faktor pengurangan tahunan $Z$ untuk tahun evaluasi 2026 ditetapkan sebesar $11\%$ dari garis referensi dasar tahun 2019.
3. **Peringkat Emisi Kapal (Grades A s.d. E)**:
   - Grade A (*Major Superior*): $\text{Ratio} \le \exp(-d_1)$
   - Grade B (*Minor Superior*): $\exp(-d_1) < \text{Ratio} \le \exp(-d_2)$
   - Grade C (*Moderate / Standard*): $\exp(-d_2) < \text{Ratio} \le \exp(d_3)$
   - Grade D (*Minor Inferior*): $\exp(d_3) < \text{Ratio} \le \exp(d_4)$
   - Grade E (*Inferior*): $\text{Ratio} > \exp(d_4)$
   Kapal yang memperoleh peringkat D selama tiga tahun berturut-turut atau peringkat E selama satu tahun kalender wajib menyusun dan mengimplementasikan *Plan of Corrective Actions* dalam SEEMP (*Ship Energy Efficiency Management Plan*) Part III sebelum diizinkan berlayar kembali.

### 3.4 A13: Baltic Exchange Time Charter Equivalent (TimeCharterEquivalentCalculator)
Dalam pasar pengangkutan laut (*freight market*), pendapatan kotor pelayaran (*gross voyage revenue*) harus dinormalisasi menjadi pendapatan bersih per hari agar dapat dibandingkan secara objektif dengan tarif kontrak sewa waktu (*time charter*):
$$\text{TCE} = \frac{\text{Pendapatan Kotor Uang Tambang} - (\text{Biaya Bunker} + \text{Biaya Pelabuhan PDA} + \text{Biaya Terusan/Canal} + \text{Komisi Broker})}{\text{Durasi Total Pelayaran (Hari Sea Passage} + \text{Hari Port Loading} + \text{Hari Port Discharging} + \text{Hari Turn-Time)}}$$
Margin operasi dihitung dengan mengurangkan TCE harian terhadap biaya operasional harian kapal (*Daily OPEX* seperti gaji pelaut, asuransi P&I/H&M, pelumas, dan amortisasi pemeliharaan berkala).

### 3.5 A14: York-Antwerp Rules 2016 General Average (GeneralAverageContributionDistributor)
Berdasarkan Rule A dan Rule XVII *York-Antwerp Rules 2016*:
1. **Tindakan General Average**: Tindakan luar biasa yang dilakukan secara sengaja dan wajar demi keselamatan bersama dari bahaya umum yang mengancam seluruh harta benda dalam petualangan maritim bersama (*common maritime adventure*).
2. **Kategori Pengorbanan & Pengeluaran**:
   - Pengorbanan Kargo (*Cargo Jettison Sacrifice*): Membuang muatan ke laut untuk meringankan sarat kapal yang kandas.
   - Pengorbanan Kapal (*Ship Damage Sacrifice*): Kerusakan mesin utama akibat dipaksa beroperasi di luar batas teknis untuk membebaskan kapal dari karang.
   - Pengeluaran Darurat (*General Average Expenditures*): Biaya sewa kapal tunda darurat (*salvage under LOF - Lloyd's Open Form*), biaya pelabuhan perlindungan (*port of refuge*), biaya pembongkaran muatan sementara, dan perbaikan darurat agar kapal dapat berlayar kembali secara selamat.
3. **Nilai Kontributif (*Contributory Values*)**:
   - Kapal: Nilai pasar kapal saat tiba di pelabuhan tujuan dalam kondisi rusak ditambah nilai pengorbanan kapal yang dipulihkan (*made good*).
   - Kargo: Nilai faktur komersial kargo saat tiba ditambah biaya asuransi dan pengorbanan kargo yang dipulihkan dikurangi ongkos angkut yang belum dibayar.
   - Uang Tambang (*Freight*): Uang tambang beresiko (*freight at risk*) dikurangi biaya operasional voyage yang dihemat akibat insiden.
4. **Proporsi Kontribusi**:
$$\text{Rasio Kontribusi General Average} = \frac{\text{Total Kerugian General Average (Pengorbanan + Pengeluaran)}}{\text{Total Nilai Kontributif Semua Kepentingan}}$$
Setiap pihak berkontribusi sebanding dengan nilai kontributif kepentingannya masing-masing.

---

## 4. Skema Akuntansi Keuangan Double-Entry Armada Maritim

Sistem pembukuan ganda maritim diatur melalui tabel `prt_fleet_ledger_entries` dengan integritas hash kriptografis SHA-256 yang saling mengunci (*hash chain*). Struktur akun buku besar mencakup:

| Kode Akun | Nama Akun Maritim | Klasifikasi Akuntansi | Deskripsi Transaksi |
|---|---|---|---|
| `prt:receivable:freight` | Piutang Uang Tambang Kargo | Aset Lancar | Ditagihkan kepada penyewa (*charterer*) saat pemuatan selesai dan Konosemen (Bill of Lading) diterbitkan. |
| `prt:revenue:freight` | Pendapatan Operasi Pengangkutan Laut | Pendapatan Operasional | Diakui saat konosemen diterbitkan atau secara proporsional sesuai metode persentase penyelesaian pelayaran. |
| `prt:expense:bunker` | Beban Konsumsi Bahan Bakar Bunker | Biaya Pokok Pendapatan (COGS) | Dibukukan saat penerimaan faktur bunker (*Bunker Delivery Note*) atau saat konsumsi riil pelayaran. |
| `prt:payable:disbursements`| Hutang Agen & Pengeluaran Pelabuhan | Kewajiban Lancar | Menampung tagihan *Proforma Disbursement Account* (PDA) dan *Final Disbursement Account* (FDA) dari agen perkapalan lokal. |
| `prt:expense:port_dues` | Beban Biaya Labuh & Tambat Pelabuhan | Biaya Pokok Pendapatan (COGS) | Mencakup tarif jasa labuh, jasa tambat dermaga, jasa tunda (*towage*), dan pandu (*pilotage*). |
| `prt:receivable:demurrage`| Piutang Denda Demurrage | Aset Lancar | Ditagihkan kepada pihak penyewa akibat keterlambatan operasional muat/bongkar melampaui batas laytime. |
| `prt:payable:despatch` | Hutang Premi Percepatan Despatch | Kewajiban Lancar | Premi insentif percepatan waktu pemuatan/pembongkaran kargo yang harus dibayarkan kembali kepada penyewa. |
| `prt:escrow:general_average`| Dana Titipan Jaminan Kerugian Laut GA| Kewajiban Bersyarat | Menampung setoran *Average Bond* dan jaminan asuransi kargo sebelum penyerahan barang kargo di pelabuhan tujuan. |

Setiap entri jurnal wajib mencatat nomor voyage, ID kapal, referensi dokumen transaksi alami (misal nomor B/L, faktur PDA, faktur bunker BDN), serta nomor referensi ledger berpasangan (*paired ledger reference*) untuk menjamin prinsip pembukuan berimbang debit = kredit.

---

## 5. Command Audit Mandiri & Deteksi Anomali (`prt:fleet-audit`)

Artisan command `prt:fleet-audit` menjalankan 6 lapis pemeriksaan integritas data:
1. **Keseimbangan Finansial Ledger**: Memastikan jumlah nilai debit sama dengan nilai kredit ($\sum \text{Debit} = \sum \text{Kredit}$) pada setiap kelompok transaksi pelayaran.
2. **Integritas Rantai Hash**: Memverifikasi kalkulasi SHA-256 pada setiap baris `prt_fleet_ledger_entries` terhadap hash rekaman sebelumnya.
3. **Konsistensi Sertifikat Statutori**: Memvalidasi bahwa seluruh sertifikat keselamatan statutori kapal (SOLAS, STCW, Marpol) tidak ada yang kedaluwarsa saat kapal berstatus operasional aktif (`IN_SERVICE`).
4. **Validasi Kapasitas Muatan**: Memastikan volume kargo yang diangkut pada dokumen konosemen tidak melampaui batas *deadweight tonnage* (DWT) kapal.
5. **Kepatuhan Alokasi Laytime**: Menverifikasi bahwa faktur demurrage atau nota despatch dihitung secara presisi sesuai *Statement of Facts* (SOF) dan klausula kontrak sewa kapal.
6. **Uji Sabotase Data (Corruption Resistance)**: Melalui opsi `--corrupt`, sistem menyimulasikan manipulasi saldo rekening atau pemalsuan rekaman transaksi untuk menguji keandalan gerbang deteksi fraud otomatis.

---

## 6. Skenario Uji Penerimaan & Simulasi Badai Selat Malaka

Skenario acceptance test utama (`MalaccaStraitStormAndGeneralAverageAcceptanceTest`) memverifikasi ketahanan modul maritim dalam menghadapi disrupsi ekstrem di dunia nyata:
1. **Kondisi Awal**: Kapal *MV Nusantara Perdana* (Supramax Bulk Carrier 58.000 DWT) berlayar membawa 50.000 MT kargo semen clinker dari Tanjung Priok ke Belawan di bawah *Charter Party* Gencon SHINC.
2. **Disrupsi Badai Selat Malaka**:
   - Angin kencang Skala Beaufort 8 dan gelombang laut tinggi memaksa kapal melakukan deviasi rute sejauh 85 mil laut dan menambah konsumsi bunker harian sebesar $18\%$.
   - Kapal mengalami kerusakan kemudi darurat (*rudder steering gear failure*) dan terancam kandas di perairan dangkal One Fathom Bank.
3. **Deklarasi General Average York-Antwerp Rules**:
   - Nahkoda mendeklarasikan situasi darurat maritim General Average.
   - Nahkoda mengontrak kapal tunda penyelamat (*Emergency Tug Salvage under LOF*) dengan komitmen biaya darurat \$65.000, serta membuang muatan kargo semen clinker sebanyak 1.500 MT senilai \$21.750 ke laut untuk memulihkan stabilitas kapal.
   - Biaya perbaikan kemudi sementara di pelabuhan perlindungan Port Klang senilai \$35.000 dimasukkan ke dalam pengeluaran bersama.
4. **Penyelesaian Kontributif GA**:
   - Mesin hitung A14 membagi total kerugian GA (\$121.750) secara proporsional kepada Pemilik Kapal (\$76.850), Pemilik Kargo (\$41.250), dan Penikmat Uang Tambang (\$3.650).
   - Seluruh kargo yang tersisa dijamin pelepasannya dengan penandatanganan *Lloyd's Average Bond* dan jaminan penjaminan asuransi laut (*Underwriter Guarantee*).
5. **Sengketa Laytime di Pelabuhan Bongkar**:
   - Penyewa mengajukan penolakan pembayaran demurrage 48 jam dengan alasan keterlambatan kapal akibat cuaca buruk.
   - Algoritma A11 membuktikan bahwa di bawah klausula SHINC dan status kapal yang telah berada di bawah *demurrage* sebelum hujan lebat turun, prinsip "once on demurrage always on demurrage" tetap berlaku secara hukum perkapalan internasional, sehingga tagihan demurrage sebesar \$36.000 dinyatakan sah secara legal dan dieksekusi pembayarannya.
