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

---

## 7. Kamus Data & Relasi 25 Tabel Armada Maritim (prt_fleet_*)

Struktur basis data Fase 180 dirancang secara normalisasi tingkat ketiga (3NF) dan mendukung audit forensik append-only:

| No | Nama Tabel Basis Data | Deskripsi Fungsi Entitas | Kunci Primer & Indeks Kunci Asing | Invarian Data Utama |
|---|---|---|---|---|
| 1 | `prt_fleet_classes` | Lembaga klasifikasi maritim (BKI, DNV, LR, ABS, NK, RINA) | `code` (PK, string), `society_name` | Tanggal survei berikutnya harus > survei terakhir. |
| 2 | `prt_fleet_vessels` | Master armada kapal niaga samudera | `id` (PK), `imo_number` (UK), `mmsi` (UK) | Check-digit IMO 7 digit valid, MMSI 9 digit maritim. |
| 3 | `prt_fleet_certificates`| Sertifikat statutori keselamatan pelayaran | `id` (PK), `vessel_id` (FK), `certificate_code` | Status otomatis EXPIRED bila tanggal masa berlaku terlampaui. |
| 4 | `prt_fleet_voyages` | Rekam jejak perjalanan pelayaran kapal | `id` (PK), `voyage_number` (UK), `vessel_id` (FK) | Dikawal oleh `VoyageStateMachine` 12 status transisi. |
| 5 | `prt_fleet_voyage_legs` | Etape atau segmen pelayaran (Ballast / Laden) | `id` (PK), `voyage_id` (FK), `leg_sequence` | Urutan leg sekuensial menaik tanpa jeda nomor. |
| 6 | `prt_fleet_charter_parties` | Kontrak perjanjian sewa kapal komersial | `id` (PK), `charter_party_number` (UK) | Dikawal oleh `CharterPartyStateMachine` 8 status. |
| 7 | `prt_fleet_port_calls` | Rencana dan realisasi kunjungan kapal di pelabuhan | `id` (PK), `voyage_id` (FK), `port_unlocode` | Waktu keberangkatan aktual wajib >= waktu tiba aktual. |
| 8 | `prt_fleet_sea_passages` | Rekaman lintas samudera & kecepatan rata-rata | `id` (PK), `voyage_id` (FK), `vessel_id` (FK) | Jarak tempuh nautical miles > 0, konsumsi fuel tercatat. |
| 9 | `prt_fleet_noon_reports` | Laporan telemetri posisi, cuaca, dan BBM 24 jam | `id` (PK), `voyage_id` (FK), `report_time` | Koordinat latitude (-90 s.d. 90), longitude (-180 s.d. 180). |
| 10 | `prt_fleet_bunkering_operations` | Operasi pengisian bahan bakar bunker di pelabuhan | `id` (PK), `vessel_id` (FK), `bdn_number` (UK) | Nomor segel BDN MARPOL dan kadar sulfur tercatat. |
| 11 | `prt_fleet_bunker_tanks` | Data tangki BBM di atas kapal (VLSFO, LSMGO, HFO) | `id` (PK), `vessel_id` (FK), `tank_code` | Volume terisi tidak boleh melebihi kapasitas desain tangki. |
| 12 | `prt_fleet_bunker_adjustments` | Penyesuaian stok BBM karena suhu atau penguapan | `id` (PK), `tank_id` (FK), `vessel_id` (FK) | Wajib menyertakan alasan teknis dan faktor densitas minyak. |
| 13 | `prt_fleet_freight_contracts` | Perjanjian kuantum angkutan kargo (COA) | `id` (PK), `contract_number` (UK), `charterer_id` | Toleransi muatan kargo lazimnya MOLOO +/- 5%. |
| 14 | `prt_fleet_laytime_calculations` | Lembar kerja kalkulasi laytime SHINC/SHEX | `id` (PK), `charter_party_id` (FK), `port_call_id` | Turn-time dan interupsi cuaca tervalidasi SOF. |
| 15 | `prt_fleet_demurrage_invoices` | Faktur denda demurrage atau nota kredit despatch | `id` (PK), `invoice_number` (UK), `charter_party_id` | Nilai tagihan dihitung dari selisih jam laytime * tarif. |
| 16 | `prt_fleet_off_hire_events` | Insiden penangguhan sewa kapal time charter | `id` (PK), `charter_party_id` (FK), `vessel_id` (FK) | Durasi off-hire mengurangi total pembayaran sewa harian. |
| 17 | `prt_fleet_pilotage_requests` | Pemesanan jasa pandu alur masuk/keluar pelabuhan | `id` (PK), `port_call_id` (FK), `pilot_name` | Mengaitkan sertifikasi pandu kelas I/II pelabuhan. |
| 18 | `prt_fleet_tug_services` | Penugasan kapal tunda sandar/lepas dermaga | `id` (PK), `port_call_id` (FK), `tug_name` | Mencatat daya bollard pull (tonase) dan durasi jam kerja. |
| 19 | `prt_fleet_berth_bookings` | Reservasi jendela tambatan dermaga via G7-K27 | `id` (PK), `port_call_id` (FK), `berth_code` | Panjang dermaga >= LOA kapal, kedalaman kolam >= draft kapal. |
| 20 | `prt_fleet_waste_disposals` | Pembuangan limbah kapal MARPOL Annex I/IV/V | `id` (PK), `port_call_id` (FK), `receipt_number` | Tanda terima reception facility berizin Kementerian LHK. |
| 21 | `prt_fleet_port_disbursements` | Rekening pencairan biaya pelabuhan (PDA/FDA) | `id` (PK), `port_call_id` (FK), `pda_reference` | Menghitung varians pengeluaran riil vs estimasi anggaran. |
| 22 | `prt_fleet_general_average_cases` | Deklarasi darurat kerugian laut York-Antwerp Rules | `id` (PK), `case_number` (UK), `voyage_id` (FK) | Menghitung kontribusi kapal, kargo, dan uang tambang. |
| 23 | `prt_fleet_weather_routings` | Rekomendasi rute pelayaran optimum anti-badai | `id` (PK), `voyage_id` (FK), `route_variant` | Mengevaluasi rute lingkaran besar vs deviasi cuaca aman. |
| 24 | `prt_fleet_crew_complements` | Daftar manifes awak kapal & sertifikasi STCW | `id` (PK), `vessel_id` (FK), `seafarer_name` | Sertifikat CoC/CoP aktif, kepatuhan jam istirahat MLC. |
| 25 | `prt_fleet_ledger_entries` | Buku besar akuntansi keuangan armada double-entry | `id` (PK), `voyage_id` (FK), `entry_hash` | Rantai hash SHA-256 saling mengunci, debit = kredit. |

---

## 8. Matriks Transisi Mesin Status Maritim

### 8.1 VoyageStateMachine (12 Status Operasional)
Siklus perjalanan kapal diatur oleh invarian berikut:

```
  +--------------+
  |   PLANNED    |
  +-------+------+
          | [Event: FIXTURE_ASSIGNED] -> Guard: Kapal laik laut (SOLAS/STCW)
          v
  +--------------+
  |  CHARTERED   |
  +-------+------+
          | [Event: STEM_BUNKER] -> Guard: Kapasitas tangki cukup
          v
  +--------------+
  |  BUNKERING   |
  +-------+------+
          | [Event: COMMENCE_LOADING] -> Guard: Palka bersih (grain clean)
          v
  +--------------+
  |   LOADING    |
  +-------+------+
          | [Event: DEPART_BERTH] -> Guard: Konosemen (B/L) & Port Clearance terbit
          v
  +--------------+
  |   DEPARTED   |
  +-------+------+
          | [Event: DROP_OUTWARD_PILOT]
          v
  +--------------+       [Event: STORM_ENCOUNTERED]       +-------------------+
  | SEA_PASSAGE  | -------------------------------------> | WEATHER_DEVIATION |
  +-------+------+                                        +---------+---------+
          |                                                         |
          | [Event: STEERING_FAILURE / FIRE]                        | [Event: STORM_PASSED]
          v                                                         v
  +--------------+                                        +-------------------+
  |   DISTRESS   | <------------------------------------+ |    SEA_PASSAGE    |
  +-------+------+                                        +-------------------+
          |
          | [Event: SALVAGE_COMPLETED / PORT_OF_REFUGE]
          v
  +--------------+
  |   ARRIVED    |
  +-------+------+
          | [Event: COMMENCE_DISCHARGE] -> Guard: Tender NOR & Palka dibuka
          v
  +--------------+
  | DISCHARGING  |
  +-------+------+
          | [Event: COMPLETE_DISCHARGE] -> Guard: Palka kosong & sapuan bersih
          v
  +--------------+
  |  COMPLETED   |
  +--------------+
```

### 8.2 CharterPartyStateMachine (8 Status Perikatan Komersial)
Perjalanan kontrak sewa kapal komersial:
1. `NEGOTIATING`: Penawaran terms, tarif freight, dan laytime terms (*Recap Negotiation*).
2. `FIXTURE_CONFIRMED`: Kesepakatan final ditandatangani kedua belah pihak (*Clean Fixture*).
3. `ON_HIRE`: Kapal diserahkan secara resmi kepada penyewa (*Delivery Notice*).
4. `SUSPENDED_OFF_HIRE`: Penangguhan sewa akibat kerusakan mekanis, mogok awak, atau deviasi sepihak kapal.
5. `REDELIVERED`: Kapal diserahkan kembali kepada pemilik di pelabuhan redelivery yang disepakati.
6. `SETTLED`: Pembayaran sewa akhir, perhitungan laytime demurrage/despatch, dan bunker ROB selesai direkonsiliasi tanpa sengketa.
7. `DISPUTED`: Terjadi perselisihan hukum yang diajukan ke arbitrase maritim.
8. `DEFAULTED`: Pihak penyewa gagal bayar (*insolvent/breach of contract*).

---

## 9. Algoritma Navigasi Lingkaran Besar & Deviasi Cuaca Optimum

Pelayaran samudera jarak jauh tidak mengikuti garis lurus peta Mercator (garis loksodrom / *rhumb line*), melainkan mengikuti busur lingkaran besar (*Great Circle Navigation*):
$$\cos(\sigma) = \sin(\phi_1)\sin(\phi_2) + \cos(\phi_1)\cos(\phi_2)\cos(\Delta\lambda)$$
$$\text{Jarak Tempuh (Nautical Miles)} = \sigma_{\text{radian}} \times \frac{180 \times 60}{\pi} = \sigma_{\text{radian}} \times 3437.74677$$
Namun, rute terpendek lingkaran besar sering kali memotong lintang tinggi yang rawan badai frontal dan gelombang tinggi. Mesin rute optimum `OceanFleetWeatherRoutingOptimumRouteService` mengevaluasi rute alternatif (*isochrone weather routing method*) untuk meminimalkan fungsi biaya multi-tujuan:
$$\min_{\text{rute } R} J(R) = \int_{R} \left( \text{Biaya Bunker}(v, \text{Beaufort}) + \text{Biaya Sewa Kapal Harian} + \text{Premi Resiko Kargo} \right) dt$$
Sehingga kapal dapat menghindari pusat siklon tropis sekaligus mempertahankan jadwal tiba tepat waktu (*Just-In-Time Port Arrival*).

---

## 10. Manajemen Perawatan Kapal (PMS), Drydocking & Special Survey

Sesuai regulasi biro klasifikasi (IACS - *International Association of Classification Societies*):
1. **Survei Antara (Intermediate Survey)**: Dilaksanakan setiap 2.5 tahun (antara tahun ke-2 dan ke-3).
2. **Survei Khusus (Special Survey & Drydocking)**: Wajib dilaksanakan setiap 5 tahun sekali di galangan kapal dok kolam (*graving dock*) atau dok apung (*floating dock*).
   - Pengukuran ketebalan pelat lambung kapal (*Ultrasonic Thickness Gauging - UTG*).
   - Pemeriksaan poros baling-baling (*Tailshaft Survey*) dan kemudi (*Rudder Clearance*).
   - Perbaikan katup laut bawah air (*Sea Chest Valves Overhaul*).
   - Pembersihan teritip lambung dan pengecatan anti-fouling (*Hull Hydroblasting & Anti-Fouling Coating*).
3. **Pencegahan Kegagalan Disposisi Aset**:
   Sistem menandai kapal yang mendekati jendela survei khusus (90 hari sebelum tanggal jatuh tempo). Jika survei terlampaui tanpa perpanjangan resmi, sertifikat kelas dibekukan (*class suspended*) dan modul memblokir seluruh operasi komersial kapal.
