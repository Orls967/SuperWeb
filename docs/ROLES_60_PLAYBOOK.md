# Playbook Operasional 60+ Peran (Roles) Konglomerasi Multi-Lini — Superwebsite

Dokumen ini mendefinisikan matriks otorisasi RBAC (*Role-Based Access Control*), tanggung jawab utama, batas wewenang persetujuan (*approval limit*), serta Standar Operasional Prosedur (SOP) lintas lini bisnis untuk seluruh 60+ peran yang terdaftar di dalam sistem konglomerasi Superwebsite.

---

## 1. Arsitektur Otorisasi & Prinsip Akses
1. **Prinsip Hak Akses Terkecil (*Least Privilege*)**: Setiap pengguna hanya diberikan izin minimal yang diperlukan untuk menuntaskan tugas fungsionalnya.
2. **Prinsip Verifikasi Ganda (*Four-Eyes Principle / Maker-Checker*)**: Transaksi bernilai material, pembukaan karantina klinis/mutu, pelepasan recall, dan otorisasi failover DR wajib melibatkan peran yang berbeda antara inisiator dan penyetuju.
3. **Pemisahan Tugas (*Segregation of Duties - SoD*)**: Peran operasional kasir/apoteker tidak dapat merangkap sebagai auditor buku besar atau administrator sistem.

---

## 2. Matriks 60+ Peran Berdasarkan 12 Lini Bisnis

| No | Kode Peran | Nama Peran | Lini Bisnis | Batas Approval Finansial | Izin Kunci (*Core Permissions*) |
|---|---|---|---|---|---|
| 1 | `super_admin` | Super Administrator Holding | Holding & Core | Tidak Terbatas | `system.manage`, `rbac.manage`, `dr.failover`, `kms.rotate` |
| 2 | `admin` | Administrator Platform | Holding & Core | Rp 500.000.000 | `system.view`, `rbac.assign`, `audit.view`, `dlq.replay` |
| 3 | `holding_cfo` | Chief Financial Officer | Holding & Core | Tidak Terbatas | `ledger.reconcile`, `dividend.approve`, `budget.override` |
| 4 | `holding_compliance` | Head of Regulatory & Compliance | Holding & Core | N/A | `compliance.audit`, `crisis.override`, `pq.verify` |
| 5 | `internal_auditor` | Senior Internal Auditor | Holding & Core | N/A (Read-Only) | `audit.read_all`, `ledger.inspect`, `hashchain.verify` |
| 6 | `risk_officer` | Chief Risk Officer | Holding & Core | Rp 1.000.000.000 | `ai.quarantine_release`, `fraud.block`, `crisis.declare` |
| 7 | `doctor` | Dokter Spesialis / Umum | Kesehatan (Hospital) | N/A | `teleconsult.conduct`, `rx.prescribe`, `emr.write` |
| 8 | `nurse` | Perawat Rawat Inap / IGD | Kesehatan (Hospital) | N/A | `triage.record`, `vitals.update`, `code_blue.trigger` |
| 9 | `pharmacist` | Apoteker Penanggung Jawab | Kesehatan (Hospital) | Rp 50.000.000 | `fefo.dispense`, `safety.override`, `recall.execute` |
| 10 | `pharmacy_technician` | Asisten Tenaga Kefarmasian | Kesehatan (Hospital) | N/A | `lot.pick`, `prescription.prepare`, `stock.count` |
| 11 | `lab_pathologist` | Dokter Spesialis Patologi Klinik | Kesehatan (Hospital) | N/A | `lab.validate`, `critical_range.notify`, `specimen.accept` |
| 12 | `lab_analyst` | Analis Laboratorium Medis | Kesehatan (Hospital) | N/A | `specimen.ingest`, `loinc.process`, `custody.sign` |
| 13 | `radiologist` | Dokter Spesialis Radiologi | Kesehatan (Hospital) | N/A | `pacs.view`, `dicom.report`, `imaging.approve` |
| 14 | `radiographer` | Radiografer Medis | Kesehatan (Hospital) | N/A | `dicom.upload`, `study.create`, `modality.operate` |
| 15 | `rs_admin` | Administrator Rumah Sakit | Kesehatan (Hospital) | Rp 100.000.000 | `hospital.billing`, `bpjs.claim`, `branch.manage` |
| 16 | `venue_manager` | General Manager Venue & Konser | Entertainment (Venue) | Rp 250.000.000 | `venue.booking`, `gate.override`, `turnstile.manage` |
| 17 | `venue_staff` | Staf Operasional Pintu Masuk | Entertainment (Venue) | N/A | `ticket.scan`, `gate.checkin`, `wristband.issue` |
| 18 | `artist_relations` | Manajer Hubungan Artis / Rider | Entertainment (Venue) | Rp 50.000.000 | `rider.approve`, `backstage.access`, `vip.escort` |
| 19 | `crowd_safety` | Petugas Keselamatan Massa & K3 | Entertainment (Venue) | N/A | `evacuation.trigger`, `capacity.lock`, `incident.log` |
| 20 | `hotel_gm` | General Manager Hotel & Resort | Pariwisata (Hotel) | Rp 200.000.000 | `room.rate_override`, `vip.complimentary`, `audit.close_day` |
| 21 | `front_office` | Front Desk Agent & Reception | Pariwisata (Hotel) | Rp 5.000.000 | `checkin.guest`, `keycard.issue`, `folio.bill` |
| 22 | `housekeeping` | Supervisor Housekeeping | Pariwisata (Hotel) | N/A | `room.clean_status`, `minibar.consume`, `amenity.request` |
| 23 | `revenue_mgr` | Revenue & Pricing Manager | Pariwisata (Hotel) | Rp 100.000.000 | `dynamic_pricing.tune`, `ota.sync`, `allotment.manage` |
| 24 | `mine_planner` | Perencana Tambang & Geologi | Energi & Tambang (Mining) | Rp 500.000.000 | `pit.plan`, `stripping_ratio.set`, `survey.commit` |
| 25 | `fleet_dispatcher` | Dispatcher Alat Berat Tambang | Energi & Tambang (Mining) | N/A | `haul_truck.assign`, `telematics.track`, `fuel.dispatch` |
| 26 | `mine_surveyor` | Juru Ukur Tambang | Energi & Tambang (Mining) | N/A | `drone.survey`, `volume.calculate`, `topo.update` |
| 27 | `hse_officer` | Petugas K3 & Lingkungan Hidup | Energi & Tambang (Mining) | N/A | `incident.halt_work`, `esg.emission_log`, `safety.audit` |
| 28 | `royalty_officer` | Petugas Royalti & Subledger ESDM | Energi & Tambang (Mining) | Rp 1.000.000.000 | `weighbridge.certify`, `royalty.calc`, `esdm.report` |
| 29 | `ev_operator` | Operator Stasiun Pengisian EV | Kendaraan Listrik (EV) | Rp 20.000.000 | `charger.session_start`, `ocpp.monitor`, `tariff.apply` |
| 30 | `ev_technician` | Teknisi Pemeliharaan Baterai EV | Kendaraan Listrik (EV) | Rp 50.000.000 | `battery_swap.verify`, `soh.diagnose`, `charger.repair` |
| 31 | `fleet_manager` | Manajer Armada Logistik Terpadu | Logistik (Fleet) | Rp 150.000.000 | `route.optimize`, `driver.assign`, `manifest.dispatch` |
| 32 | `logistics_driver` | Pengemudi Ekspedisi & Ambulance | Logistik (Fleet) | N/A | `epod.sign`, `iot_telematics.ping`, `temp_log.submit` |
| 33 | `cold_chain_supervisor`| Pengawas Rantai Dingin Bio-Farma | Logistik (Cold Chain) | Rp 75.000.000 | `temp_excursion.flag`, `containment.seal`, `pharma.clear` |
| 34 | `wms_supervisor` | Supervisor Pergudangan & Hub | Rantai Pasok (WMS) | Rp 100.000.000 | `bin.reallocate`, `crossdock.transfer`, `cycle_count.audit` |
| 35 | `wms_picker` | Petugas Picking & Packing | Rantai Pasok (WMS) | N/A | `barcode.scan`, `pallet.move`, `shelf.replenish` |
| 36 | `procurement_manager` | Manajer Pengadaan & Vendor | Pengadaan (Procurement) | Rp 500.000.000 | `po.approve`, `vendor.evaluate`, `contract.sign` |
| 37 | `procurement_buyer` | Staf Buyer Pengadaan Bahan | Pengadaan (Procurement) | Rp 50.000.000 | `rfq.issue`, `vendor.compare`, `gr_ir.match` |
| 38 | `store_manager` | Manajer Gerai Ritel / Restoran | F&B / Ritel (Resto) | Rp 25.000.000 | `pos.refund`, `inventory.adjust`, `cashier_shift.close` |
| 39 | `cashier` | Kasir Point of Sale (POS) | F&B / Ritel (Resto) | Rp 2.000.000 | `pos.checkout`, `cash.tender`, `qris.accept` |
| 40 | `kitchen_chef` | Kepala Dapur / Kitchen Leader | F&B / Cloud Kitchen | Rp 10.000.000 | `kds.bump`, `recipe.costing`, `ingredient.spoilage` |
| 41 | `mall_leasing_manager`| Manajer Penyewaan Tenant Mall | Properti (Mall) | Rp 300.000.000 | `tenant.lease_sign`, `turnover_rent.calc`, `unit.reserve` |
| 42 | `mall_meter_officer` | Petugas Pencatat Meter Utilitas | Properti (Mall) | N/A | `kwh.record`, `water.record`, `submeter.inspect` |
| 43 | `bank_treasurer` | Bendahara Rekonsiliasi Bank | Fintech & Wealth (Fin) | Rp 2.000.000.000 | `escrow.release`, `clearing.settle`, `va.match` |
| 44 | `wm_advisor` | Penasihat Keuangan Robo-Advisor | Fintech & Wealth (WM) | Rp 500.000.000 | `portfolio.rebalance`, `kyc.approve`, `rwa.invest` |
| 45 | `contract_lawyer` | Tim Legal & Manajer Kontrak | Legal (Contract) | Rp 1.000.000.000 | `contract.draft`, `clause.sign`, `hash.seal` |
| 46 | `mfg_plant_manager` | General Manager Pabrik / Plant | Manufaktur (MFG) | Rp 1.000.000.000 | `work_order.release`, `mrp.run`, `capacity.plan` |
| 47 | `mfg_line_operator` | Operator Lini Produksi Manufaktur | Manufaktur (MFG) | N/A | `bom.consume`, `step.complete`, `scrap.record` |
| 48 | `qms_inspector` | Inspektur Kendali Mutu (QC/QA) | Manufaktur (MFG) | Rp 100.000.000 | `qc.pass`, `lot.quarantine`, `capa.open` |
| 49 | `agriculture_manager` | Manajer Kebun / Perkebunan | Agribisnis (Agri) | Rp 200.000.000 | `harvest.schedule`, `fertilizer.budget`, `weather.react` |
| 50 | `estate_foreman` | Mandor Lapangan Perkebunan | Agribisnis (Agri) | N/A | `yield.measure`, `worker.attendance`, `pest.report` |
| 51 | `fishery_captain` | Nakhoda Kapal Penangkap Ikan | Maritim (Fishery) | Rp 100.000.000 | `catch.log`, `zone.vms`, `cold_hold.temperature` |
| 52 | `telecom_engineer` | Insinyur Jaringan Menara Seluler | Infrastruktur (Telco) | Rp 150.000.000 | `bts.power_adjust`, `fiber.splice`, `sla.monitor` |
| 53 | `school_principal` | Kepala Sekolah / Rektor Kampus | Pendidikan (Edu) | Rp 100.000.000 | `tuition.waive`, `curriculum.lock`, `degree.certify` |
| 54 | `academic_admin` | Staf Tata Usaha Akademik | Pendidikan (Edu) | N/A | `student.enroll`, `attendance.log`, `transcript.print` |
| 55 | `waste_manager` | Manajer Pengolahan Limbah B3 | Lingkungan (Waste) | Rp 75.000.000 | `manifest.sign`, `incinerator.log`, `klhk.submit` |
| 56 | `energy_auditor` | Auditor Efisiensi Energi & Karbon | Hijau & ESG (ESG) | N/A | `scope1_2_3.calc`, `carbon_credit.verify`, `grid.audit` |
| 57 | `security_guard` | Petugas Satuan Pengamanan (Satpam) | Fasilitas & Keamanan | N/A | `visitor.badge`, `cctv.monitor`, `gate_barrier.open` |
| 58 | `cleaning_staff` | Staf Kebersihan & Sanitasi | Fasilitas & Sanitasi | N/A | `sanitation.checklist`, `biohazard_bin.empty`, `tag.ok` |
| 59 | `customer_service` | Layanan Pelanggan & Helpdesk | Layanan Publik | Rp 1.000.000 | `ticket.resolve`, `faq.answer`, `complaint.escalate` |
| 60 | `it_support` | Staf Dukungan IT Lapangan | Teknologi Informasi | N/A | `terminal.reboot`, `printer.fix`, `offline_sync.force` |

---

## 3. SOP Lintas Peran Terpadu (*Integrated Cross-Role SOP*)

### SOP-01: Prosedur Kode Biru (*Code Blue*) & Pasien Gawat Darurat
1. **Inisiasi**: Perawat (`nurse`) mendeteksi henti jantung/napas di bangsal -> klik pemicu *Code Blue* di tablet mobile.
2. **Siaga Otomatis**: Layanan `EventSpineService` memancarkan event `hospital.code_blue_triggered`.
3. **Respon Dokter**: Dokter Jaga (`doctor`) menerima notifikasi push prioritas tinggi dalam $< 5$ detik dengan lokasi ruangan presisi.
4. **Penyaluran Obat Cito**: Apoteker (`pharmacist`) otomatis menerima daftar kit resusitasi darurat dan memprioritaskan dispensing tanpa menunggu verifikasi pembayaran asuransi terlebih dahulu (*clinical life-safety bypass*).
5. **Pencatatan**: Setelah stabilisasi, Dokter dan Perawat melengkapi rekam medis terenkripsi AES-256 (`hsp_patient_records`) dan buku besar mencatat penggunaan obat.

### SOP-02: Prosedur Penarikan Obat Kritis (*Emergency Product Recall*)
1. **Deklarasi**: BPOM / Head of Compliance (`holding_compliance`) menerbitkan surat recall obat tercemar.
2. **Karantina Instan**: Chief Risk Officer (`risk_officer`) memanggil `CrisisProductRecallService::declareEmergencyRecall()`.
3. **Penguncian POS**: Dalam 100 ms, seluruh modul POS Resto/Ritel (`cashier`) dan E-Farmasi (`pharmacist`) dilarang menjual lot terdampak.
4. **Trisula Pasien**: Sistem menelusuri seluruh pasien yang menerima resep lot tersebut dan mengirim peringatan SMS/WA darurat.
5. **Pemusnahan Limbah B3**: Manajer Limbah (`waste_manager`) menerbitkan manifes pemusnahan resmi limbah farmasi.

### SOP-03: Penanganan Kegagalan Quorum DR Multi-Wilayah
1. **Deteksi**: Node witness Raft mendeteksi wilayah primer *unresponsive* $> 3.000$ ms.
2. **Evaluasi Kuorum**: Bila kuorum 2/3 terpenuhi di wilayah sekunder, service `DisasterRecoveryFailoverService` mengeksekusi promosi master sekunder secara otomatis dengan RPO=0.
3. **Degradasi Anggun**: Bila kuorum tidak tercapai (misal split-brain jaringan terbelah dua), node otomatis mengunci diri ke status `READ_ONLY` untuk mencegah inkonsistensi data ganda (*zero dual-master write*).
4. **Otorisasi Super Admin**: Super Administrator (`super_admin`) mengeksekusi manual failover drill bila diperlukan verifikasi fisik.


---

## 4. Profil Operasional Lengkap 60 Peran Konglomerasi

Berikut adalah spesifikasi mendalam untuk seluruh 60 peran terdaftar dalam arsitektur RBAC Superwebsite, memuat batas otorisasi finansial, izin teknis, rute aman/terlarang, rutinitas harian, dan eskalasi darurat:

### 4.1 Peran #1: `super_admin` — Super Administrator Holding
- **Lini Bisnis / Domain**: Holding & Core
- **Batas Otorisasi Finansial**: `Tidak Terbatas`
- **Izin Kunci (*Core Abilities*)**: `system.manage`, `rbac.manage`, `dr.failover`, `kms.rotate`
- **Rute Terotorisasi**: `GET /admin/*`, `POST /admin/system/*`, `POST /admin/dr/*`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemeriksaan sistem global, rotasi kunci KMS, audit akses superuser.
- **Pemetaan User Story Resmi**: US-1.1, US-6.1, US-26.1, US-66.1, US-100.1, US-101.1
- **SOP Eskalasi & Insiden**: SOP-03 Failover DR, isolasi darurat node compromised.

### 4.2 Peran #2: `admin` — Administrator Platform
- **Lini Bisnis / Domain**: Holding & Core
- **Batas Otorisasi Finansial**: `Rp 500.000.000`
- **Izin Kunci (*Core Abilities*)**: `system.view`, `rbac.assign`, `audit.view`, `dlq.replay`
- **Rute Terotorisasi**: `GET /admin/*`, `POST /admin/users/*`, `POST /admin/dlq/replay`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Monitoring antrean webhook DLQ, provisi akun pengguna, verifikasi log.
- **Pemetaan User Story Resmi**: US-6.2, US-17.1, US-26.2, US-55.1, US-102.1
- **SOP Eskalasi & Insiden**: Replay failed webhooks, reset credential berkala.

### 4.3 Peran #3: `holding_cfo` — Chief Financial Officer
- **Lini Bisnis / Domain**: Holding & Core
- **Batas Otorisasi Finansial**: `Tidak Terbatas`
- **Izin Kunci (*Core Abilities*)**: `ledger.reconcile`, `dividend.approve`, `budget.override`
- **Rute Terotorisasi**: `GET /finance/*`, `POST /finance/dividends/approve`, `POST /finance/budget/override`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Review laporan konsolidasi laba rugi 12 lini, otorisasi dividen RWA.
- **Pemetaan User Story Resmi**: US-1.2, US-48.1, US-54.1, US-71.1, US-73.1
- **SOP Eskalasi & Insiden**: Eskalasi selisih kas grup > Rp 100M, intercompany netting mismatch.

### 4.4 Peran #4: `holding_compliance` — Head of Regulatory & Compliance
- **Lini Bisnis / Domain**: Holding & Core
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `compliance.audit`, `crisis.override`, `pq.verify`
- **Rute Terotorisasi**: `GET /compliance/*`, `POST /compliance/quarantine`, `POST /compliance/reports`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemantauan kepatuhan OJK/BI/BPOM, verifikasi rantai post-quantum.
- **Pemetaan User Story Resmi**: US-27.1, US-29.1, US-57B.1, US-66.2, US-100.2
- **SOP Eskalasi & Insiden**: Deklarasi krisis kepatuhan, pelaporan instan regulator.

### 4.5 Peran #5: `internal_auditor` — Senior Internal Auditor
- **Lini Bisnis / Domain**: Holding & Core
- **Batas Otorisasi Finansial**: `N/A (Read-Only)`
- **Izin Kunci (*Core Abilities*)**: `audit.read_all`, `ledger.inspect`, `hashchain.verify`
- **Rute Terotorisasi**: `GET /audit/*`, `GET /reports/*` (DILARANG: Semua POST/PUT/DELETE)
- **Rute Terlarang Mutlak**: Semua rute mutating (`POST`, `PUT`, `DELETE`, `PATCH`). Auditor terikat prinsip read-only.
- **Rutinitas Operasional Harian**: Inspeksi hash-chain paspor kendaraan, audit trail mutasi stok & GL.
- **Pemetaan User Story Resmi**: US-1.3, US-5.1, US-28.1, US-57B.2, US-100.3
- **SOP Eskalasi & Insiden**: Penerbitan Surat Temuan Audit (STA), freeze investigasi akun.

### 4.6 Peran #6: `risk_officer` — Chief Risk Officer
- **Lini Bisnis / Domain**: Holding & Core
- **Batas Otorisasi Finansial**: `Rp 1.000.000.000`
- **Izin Kunci (*Core Abilities*)**: `ai.quarantine_release`, `fraud.block`, `crisis.declare`
- **Rute Terotorisasi**: `GET /risk/*`, `POST /risk/block`, `POST /risk/quarantine/release`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Analisis risiko eksposur valas, mitigasi default kredit & klaim fraud.
- **Pemetaan User Story Resmi**: US-48.2, US-50.1, US-72.1, US-99.1, US-100.4
- **SOP Eskalasi & Insiden**: Pemberhentian sementara perdagangan bursa b2b lelang.

### 4.7 Peran #7: `doctor` — Dokter Spesialis / Umum
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `teleconsult.conduct`, `rx.prescribe`, `emr.write`
- **Rute Terotorisasi**: `GET /hospital/emr/*`, `POST /hospital/teleconsult/submit`, `POST /hospital/rx`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemeriksaan pasien poli & rawat inap, penulisan resep elektronik FEFO.
- **Pemetaan User Story Resmi**: US-87.1, US-87.2, US-88.1, US-104.1, US-104.2
- **SOP Eskalasi & Insiden**: SOP-01 Penanganan Code Blue henti jantung di bangsal.

### 4.8 Peran #8: `nurse` — Perawat Rawat Inap / IGD
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `triage.record`, `vitals.update`, `code_blue.trigger`
- **Rute Terotorisasi**: `GET /hospital/wards/*`, `POST /hospital/triage`, `POST /hospital/vitals`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Triase gawat darurat, pencatatan tanda vital per jam, administrasi obat.
- **Pemetaan User Story Resmi**: US-87.3, US-87.4, US-88.2, US-104.3
- **SOP Eskalasi & Insiden**: Pemicuan tombol darurat Code Blue, evakuasi pasien bangsal.

### 4.9 Peran #9: `pharmacist` — Apoteker Penanggung Jawab
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `Rp 50.000.000`
- **Izin Kunci (*Core Abilities*)**: `fefo.dispense`, `safety.override`, `recall.execute`
- **Rute Terotorisasi**: `GET /hospital/pharmacy/*`, `POST /hospital/pharmacy/dispense`, `POST /hospital/recall`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Verifikasi resep, skrining interaksi obat, dispensing FEFO lot obat.
- **Pemetaan User Story Resmi**: US-87.5, US-88.3, US-104.4, US-104.5
- **SOP Eskalasi & Insiden**: SOP-02 Penarikan darurat (emergency recall) batch obat terkontaminasi.

### 4.10 Peran #10: `pharmacy_technician` — Asisten Tenaga Kefarmasian
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `lot.pick`, `prescription.prepare`, `stock.count`
- **Rute Terotorisasi**: `GET /hospital/pharmacy/stock`, `POST /hospital/pharmacy/pick`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pengambilan obat dari rak FEFO, pelabelan etiket resep, opname stok harian.
- **Pemetaan User Story Resmi**: US-87.5, US-104.4
- **SOP Eskalasi & Insiden**: Pelaporan stok obat kadaluarsa < 30 hari ke apoteker.

### 4.11 Peran #11: `lab_pathologist` — Dokter Spesialis Patologi Klinik
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `lab.validate`, `critical_range.notify`, `specimen.accept`
- **Rute Terotorisasi**: `GET /hospital/lab/*`, `POST /hospital/lab/validate`, `POST /hospital/critical-notify`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Validasi hasil pemeriksaan darah/urine berstandar LOINC, notifikasi nilai kritis.
- **Pemetaan User Story Resmi**: US-87.2, US-88.4, US-105.1, US-105.2
- **SOP Eskalasi & Insiden**: Pemberitahuan telepon segera untuk nilai laboratorium kritis < 15 menit.

### 4.12 Peran #12: `lab_analyst` — Analis Laboratorium Medis
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `specimen.ingest`, `loinc.process`, `custody.sign`
- **Rute Terotorisasi**: `GET /hospital/lab/queue`, `POST /hospital/lab/ingest`, `POST /hospital/lab/run`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pengambilan spesimen darah, pemrosesan mesin analyzer, input data mentah LOINC.
- **Pemetaan User Story Resmi**: US-88.4, US-105.3, US-105.4
- **SOP Eskalasi & Insiden**: Penolakan spesimen hemolisis atau beku dan request pengambilan ulang.

### 4.13 Peran #13: `radiologist` — Dokter Spesialis Radiologi
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `pacs.view`, `dicom.report`, `imaging.approve`
- **Rute Terotorisasi**: `GET /hospital/pacs/*`, `POST /hospital/pacs/report`, `POST /hospital/pacs/approve`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Ekspertise hasil foto Rontgen/CT/MRI di viewer PACS DICOM, pengesahan laporan.
- **Pemetaan User Story Resmi**: US-88.5, US-105.5, US-105.6
- **SOP Eskalasi & Insiden**: Temuan kritis perdarahan intrakranial/pneumotoraks lapor langsung ke DPJP.

### 4.14 Peran #14: `radiographer` — Radiografer Medis
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `dicom.upload`, `study.create`, `modality.operate`
- **Rute Terotorisasi**: `GET /hospital/modalities/*`, `POST /hospital/pacs/upload`, `POST /hospital/study`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pelaksanaan pemindaian radiologi pasien, transmisi metadata DICOM ke PACS server.
- **Pemetaan User Story Resmi**: US-105.5, US-105.7
- **SOP Eskalasi & Insiden**: Kalibrasi radiasi harian dan penanganan malfungsi mesin sinar-X.

### 4.15 Peran #15: `rs_admin` — Administrator Rumah Sakit
- **Lini Bisnis / Domain**: Kesehatan (Hospital)
- **Batas Otorisasi Finansial**: `Rp 100.000.000`
- **Izin Kunci (*Core Abilities*)**: `hospital.billing`, `bpjs.claim`, `branch.manage`
- **Rute Terotorisasi**: `GET /hospital/billing/*`, `POST /hospital/billing/finalize`, `POST /hospital/bpjs/claim`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penggabungan billing tindakan, pengajuan klaim BPJS INA-CBG, rekonsiliasi kasir RS.
- **Pemetaan User Story Resmi**: US-88.1, US-88.2, US-88.5, US-104.1
- **SOP Eskalasi & Insiden**: Penyelesaian penolakan klaim BPJS (dispute INA-CBG) dalam 3x24 jam.

### 4.16 Peran #16: `venue_manager` — General Manager Venue & Konser
- **Lini Bisnis / Domain**: Entertainment (Venue)
- **Batas Otorisasi Finansial**: `Rp 250.000.000`
- **Izin Kunci (*Core Abilities*)**: `venue.booking`, `gate.override`, `turnstile.manage`
- **Rute Terotorisasi**: `GET /venue/*`, `POST /venue/event/create`, `POST /venue/gate/override`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penjadwalan konser/event, penetapan kuota zona festival, manajemen vendor.
- **Pemetaan User Story Resmi**: US-89.1, US-89.2, US-90.1, US-90.2
- **SOP Eskalasi & Insiden**: Evakuasi massa darurat overkapasitas dan penutupan gate instan.

### 4.17 Peran #17: `venue_staff` — Staf Operasional Pintu Masuk
- **Lini Bisnis / Domain**: Entertainment (Venue)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `ticket.scan`, `gate.checkin`, `wristband.issue`
- **Rute Terotorisasi**: `GET /venue/turnstile`, `POST /venue/scan`, `POST /venue/wristband`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemindaian tiket QR turnstile, pemasangan gelang RFID cashless wristband.
- **Pemetaan User Story Resmi**: US-89.3, US-89.4, US-90.3
- **SOP Eskalasi & Insiden**: Penanganan tiket palsu/duplikat dan pengalihan ke helpdesk gate.

### 4.18 Peran #18: `artist_relations` — Manajer Hubungan Artis / Rider
- **Lini Bisnis / Domain**: Entertainment (Venue)
- **Batas Otorisasi Finansial**: `Rp 50.000.000`
- **Izin Kunci (*Core Abilities*)**: `rider.approve`, `backstage.access`, `vip.escort`
- **Rute Terotorisasi**: `GET /venue/riders/*`, `POST /venue/rider/approve`, `POST /venue/backstage/grant`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemenuhan technical & hospitality rider artis internasional, kontrol akses backstage.
- **Pemetaan User Story Resmi**: US-90.4, US-90.5
- **SOP Eskalasi & Insiden**: Penyediaan darurat penggantian instrumen/peralatan panggung < 30 menit.

### 4.19 Peran #19: `crowd_safety` — Petugas Keselamatan Massa & K3
- **Lini Bisnis / Domain**: Entertainment (Venue)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `evacuation.trigger`, `capacity.lock`, `incident.log`
- **Rute Terotorisasi**: `GET /venue/sensors/*`, `POST /venue/lock-capacity`, `POST /venue/evacuation`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemantauan sensor kepadatan penonton (heat-map), pengendalian pintu darurat.
- **Pemetaan User Story Resmi**: US-89.5, US-90.6
- **SOP Eskalasi & Insiden**: Pemicuan pembukaan pintu evakuasi otomatis bila kepadatan zona > 4 org/m2.

### 4.20 Peran #20: `hotel_gm` — General Manager Hotel & Resort
- **Lini Bisnis / Domain**: Pariwisata (Hotel)
- **Batas Otorisasi Finansial**: `Rp 200.000.000`
- **Izin Kunci (*Core Abilities*)**: `room.rate_override`, `vip.complimentary`, `audit.close_day`
- **Rute Terotorisasi**: `GET /hotel/*`, `POST /hotel/rate-override`, `POST /hotel/night-audit/approve`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Otorisasi diskon grup besar, persetujuan complimentary suite VIP, review night audit.
- **Pemetaan User Story Resmi**: US-91.1, US-91.2, US-92.1, US-92.2
- **SOP Eskalasi & Insiden**: Penanganan keluhan tamu VIP bintang lima dan klaim asuransi fasilitas hotel.

### 4.21 Peran #21: `front_office` — Front Desk Agent & Reception
- **Lini Bisnis / Domain**: Pariwisata (Hotel)
- **Batas Otorisasi Finansial**: `Rp 5.000.000`
- **Izin Kunci (*Core Abilities*)**: `checkin.guest`, `keycard.issue`, `folio.bill`
- **Rute Terotorisasi**: `GET /hotel/rooms/status`, `POST /hotel/checkin`, `POST /hotel/keycard`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Proses check-in/out tamu, penerbitan smart keycard RFID/QR, pemisahan tagihan folio.
- **Pemetaan User Story Resmi**: US-91.3, US-91.4, US-92.3, US-92.4
- **SOP Eskalasi & Insiden**: Pencarian kamar alternatif bila terjadi relokasi overbooking kamar sekelas.

### 4.22 Peran #22: `housekeeping` — Supervisor Housekeeping
- **Lini Bisnis / Domain**: Pariwisata (Hotel)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `room.clean_status`, `minibar.consume`, `amenity.request`
- **Rute Terotorisasi**: `GET /hotel/housekeeping/*`, `POST /hotel/room/status`, `POST /hotel/minibar`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Inspeksi kebersihan kamar, update status `CLEAN/DIRTY`, inventaris konsumsi minibar.
- **Pemetaan User Story Resmi**: US-91.5, US-92.5
- **SOP Eskalasi & Insiden**: Pencatatan barang tertinggal (Lost & Found) bernilai tinggi ke brankas FO.

### 4.23 Peran #23: `revenue_mgr` — Revenue & Pricing Manager
- **Lini Bisnis / Domain**: Pariwisata (Hotel)
- **Batas Otorisasi Finansial**: `Rp 100.000.000`
- **Izin Kunci (*Core Abilities*)**: `dynamic_pricing.tune`, `ota.sync`, `allotment.manage`
- **Rute Terotorisasi**: `GET /hotel/rates/*`, `POST /hotel/rates/adjust`, `POST /hotel/ota/sync`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pengaturan algoritma surge pricing kamar, sinkronisasi kuota OTA via Channel Manager.
- **Pemetaan User Story Resmi**: US-91.6, US-92.6
- **SOP Eskalasi & Insiden**: Penguncian darurat channel rate jika terjadi kesalahan harga OTA (parity glitch).

### 4.24 Peran #24: `mine_planner` — Perencana Tambang & Geologi
- **Lini Bisnis / Domain**: Energi & Tambang (Mining)
- **Batas Otorisasi Finansial**: `Rp 500.000.000`
- **Izin Kunci (*Core Abilities*)**: `pit.plan`, `stripping_ratio.set`, `survey.commit`
- **Rute Terotorisasi**: `GET /mining/plans/*`, `POST /mining/pit/create`, `POST /mining/survey`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penyusunan rencana penambangan bulanan, penetapan rasio kupas batuan (Stripping Ratio).
- **Pemetaan User Story Resmi**: US-93.1, US-93.2, US-94.1
- **SOP Eskalasi & Insiden**: Koreksi arah galian tambang bila ditemukan sesar batuan atau longsoran pit.

### 4.25 Peran #25: `fleet_dispatcher` — Dispatcher Alat Berat Tambang
- **Lini Bisnis / Domain**: Energi & Tambang (Mining)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `haul_truck.assign`, `telematics.track`, `fuel.dispatch`
- **Rute Terotorisasi**: `GET /mining/dispatch/*`, `POST /mining/assign-truck`, `POST /mining/fuel`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Alokasi dump truck ke excavator via Fleet Management System, monitor konsumsi solar.
- **Pemetaan User Story Resmi**: US-93.3, US-93.4, US-94.2
- **SOP Eskalasi & Insiden**: Penghentian operasi alat tambang saat hujan lebat atau jalan tambang licin.

### 4.26 Peran #26: `mine_surveyor` — Juru Ukur Tambang
- **Lini Bisnis / Domain**: Energi & Tambang (Mining)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `drone.survey`, `volume.calculate`, `topo.update`
- **Rute Terotorisasi**: `GET /mining/topo/*`, `POST /mining/drone-upload`, `POST /mining/volume`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pengukuran topografi berkala menggunakan drone LiDAR, hitung kubikasi batubara digali.
- **Pemetaan User Story Resmi**: US-93.5, US-94.3
- **SOP Eskalasi & Insiden**: Pemberitahuan dini ketidakstabilan lereng tambang (slope monitoring).

### 4.27 Peran #27: `hse_officer` — Petugas K3 & Lingkungan Hidup
- **Lini Bisnis / Domain**: Energi & Tambang (Mining)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `incident.halt_work`, `esg.emission_log`, `safety.audit`
- **Rute Terotorisasi**: `GET /mining/hse/*`, `POST /mining/halt-work`, `POST /mining/incident`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Inspeksi kepatuhan K3 lapangan, penerbitan izin kerja berbahaya (LOTO/Confined Space).
- **Pemetaan User Story Resmi**: US-40.1, US-93.6, US-94.4
- **SOP Eskalasi & Insiden**: Penerbitan surat Stop-Work Order instan atas pelanggaran keselamatan kritis.

### 4.28 Peran #28: `royalty_officer` — Petugas Royalti & Subledger ESDM
- **Lini Bisnis / Domain**: Energi & Tambang (Mining)
- **Batas Otorisasi Finansial**: `Rp 1.000.000.000`
- **Izin Kunci (*Core Abilities*)**: `weighbridge.certify`, `royalty.calc`, `esdm.report`
- **Rute Terotorisasi**: `GET /mining/royalty/*`, `POST /mining/weighbridge/seal`, `POST /mining/royalty/calc`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Sertifikasi tiket jembatan timbang terkalibrasi, kalkulasi akrual royalti PNBP e-PNBP.
- **Pemetaan User Story Resmi**: US-94.5, US-94.6
- **SOP Eskalasi & Insiden**: Investigasi selisih timbangan tambang vs pelabuhan bongkar > 0.5%.

### 4.29 Peran #29: `ev_operator` — Operator Stasiun Pengisian EV
- **Lini Bisnis / Domain**: Kendaraan Listrik (EV)
- **Batas Otorisasi Finansial**: `Rp 20.000.000`
- **Izin Kunci (*Core Abilities*)**: `charger.session_start`, `ocpp.monitor`, `tariff.apply`
- **Rute Terotorisasi**: `GET /ev/chargers/*`, `POST /ev/session/start`, `POST /ev/session/stop`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemantauan sesi pengisian daya cepat OCPP 2.0.1, penagihan tarif kWh ke pengguna.
- **Pemetaan User Story Resmi**: US-69.1, US-69.2, US-69.3
- **SOP Eskalasi & Insiden**: Tombol Emergency Stop charger SPKLU saat terdeteksi kebocoran arus ground fault.

### 4.30 Peran #30: `ev_technician` — Teknisi Pemeliharaan Baterai EV
- **Lini Bisnis / Domain**: Kendaraan Listrik (EV)
- **Batas Otorisasi Finansial**: `Rp 50.000.000`
- **Izin Kunci (*Core Abilities*)**: `battery_swap.verify`, `soh.diagnose`, `charger.repair`
- **Rute Terotorisasi**: `GET /ev/telemetry/*`, `POST /ev/battery/swap`, `POST /ev/maintenance`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Diagnosa State of Health (SoH) baterai pack, eksekusi battery swapping otomatis.
- **Pemetaan User Story Resmi**: US-69.4, US-69.5
- **SOP Eskalasi & Insiden**: Isolasi pendingin baterai pack mengalami thermal runaway di stasiun swap.

### 4.31 Peran #31: `fleet_manager` — Manajer Armada Logistik Terpadu
- **Lini Bisnis / Domain**: Logistik (Fleet)
- **Batas Otorisasi Finansial**: `Rp 150.000.000`
- **Izin Kunci (*Core Abilities*)**: `route.optimize`, `driver.assign`, `manifest.dispatch`
- **Rute Terotorisasi**: `GET /logistics/fleet/*`, `POST /logistics/routes/plan`, `POST /logistics/dispatch`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Perencanaan rute truk multimoda, pengawasan kepatuhan batas jam mengemudi sopir.
- **Pemetaan User Story Resmi**: US-20.1, US-20.2, US-22.1, US-70.1
- **SOP Eskalasi & Insiden**: Pengalihan rute darurat armada akibat bencana alam atau kemacetan tol parah.

### 4.32 Peran #32: `logistics_driver` — Pengemudi Ekspedisi & Ambulance
- **Lini Bisnis / Domain**: Logistik (Fleet)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `epod.sign`, `iot_telematics.ping`, `temp_log.submit`
- **Rute Terotorisasi**: `GET /driver/orders`, `POST /driver/epod`, `POST /driver/ping`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemeriksaan kendaraan pra-jalan, konfirmasi penerimaan elektronik (ePoD), live GPS ping.
- **Pemetaan User Story Resmi**: US-21.1, US-22.2, US-23.1
- **SOP Eskalasi & Insiden**: Pelaporan kecelakaan lalu lintas atau mogok jalan via aplikasi driver.

### 4.33 Peran #33: `cold_chain_supervisor` — Pengawas Rantai Dingin Bio-Farma
- **Lini Bisnis / Domain**: Logistik (Cold Chain)
- **Batas Otorisasi Finansial**: `Rp 75.000.000`
- **Izin Kunci (*Core Abilities*)**: `temp_excursion.flag`, `containment.seal`, `pharma.clear`
- **Rute Terotorisasi**: `GET /logistics/cold-chain/*`, `POST /logistics/temp-alert`, `POST /logistics/clear`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pengawasan suhu kontainer reefer 2-8°C, karantina batch vaksin jika suhu ekskursi.
- **Pemetaan User Story Resmi**: US-22.3, US-80.1, US-80.2
- **SOP Eskalasi & Insiden**: Protokol pemindahan muatan darurat ke dry-ice box bila genset reefer mati.

### 4.34 Peran #34: `wms_supervisor` — Supervisor Pergudangan & Hub
- **Lini Bisnis / Domain**: Rantai Pasok (WMS)
- **Batas Otorisasi Finansial**: `Rp 100.000.000`
- **Izin Kunci (*Core Abilities*)**: `bin.reallocate`, `crossdock.transfer`, `cycle_count.audit`
- **Rute Terotorisasi**: `GET /wms/bins/*`, `POST /wms/cycle-count`, `POST /wms/transfer`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Manajemen zona bin gudang, audit cycle count empat mata, otorisasi cross-docking.
- **Pemetaan User Story Resmi**: US-41.1, US-41.2, US-82.1, US-82.2
- **SOP Eskalasi & Insiden**: Investigasi selisih barang gudang bernilai tinggi dan penyesuaian inventory.

### 4.35 Peran #35: `wms_picker` — Petugas Picking & Packing
- **Lini Bisnis / Domain**: Rantai Pasok (WMS)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `barcode.scan`, `pallet.move`, `shelf.replenish`
- **Rute Terotorisasi**: `GET /wms/tasks/pick`, `POST /wms/scan-barcode`, `POST /wms/task/complete`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemindaian barcode barang di rak, pengemasan paket sesuai standar packing list.
- **Pemetaan User Story Resmi**: US-41.3, US-41.4
- **SOP Eskalasi & Insiden**: Pelaporan kemasan barang rusak atau barcode robek ke supervisor.

### 4.36 Peran #36: `procurement_manager` — Manajer Pengadaan & Vendor
- **Lini Bisnis / Domain**: Pengadaan (Procurement)
- **Batas Otorisasi Finansial**: `Rp 500.000.000`
- **Izin Kunci (*Core Abilities*)**: `po.approve`, `vendor.evaluate`, `contract.sign`
- **Rute Terotorisasi**: `GET /procurement/*`, `POST /procurement/po/approve`, `POST /procurement/vendor/eval`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Evaluasi tender vendor, persetujuan PO material utama, negosiasi trade credit term.
- **Pemetaan User Story Resmi**: US-32.1, US-33.1, US-33.2, US-84.1
- **SOP Eskalasi & Insiden**: Eskalasi wanprestasi pemasok kritis dan aktivasi supplier cadangan BCP.

### 4.37 Peran #37: `procurement_buyer` — Staf Buyer Pengadaan Bahan
- **Lini Bisnis / Domain**: Pengadaan (Procurement)
- **Batas Otorisasi Finansial**: `Rp 50.000.000`
- **Izin Kunci (*Core Abilities*)**: `rfq.issue`, `vendor.compare`, `gr_ir.match`
- **Rute Terotorisasi**: `GET /procurement/rfq/*`, `POST /procurement/rfq/issue`, `POST /procurement/po/draft`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penerbitan RFQ ke portal vendor, perbandingan penawaran harga, verifikasi GR/IR.
- **Pemetaan User Story Resmi**: US-32.2, US-33.3, US-34.1
- **SOP Eskalasi & Insiden**: Klarifikasi diskrepansi kuantitas PO vs delivery note dari supplier.

### 4.38 Peran #38: `store_manager` — Manajer Gerai Ritel / Restoran
- **Lini Bisnis / Domain**: F&B / Ritel (Resto)
- **Batas Otorisasi Finansial**: `Rp 25.000.000`
- **Izin Kunci (*Core Abilities*)**: `pos.refund`, `inventory.adjust`, `cashier_shift.close`
- **Rute Terotorisasi**: `GET /store/*`, `POST /store/refund/approve`, `POST /store/shift/close`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Otorisasi pembatalan pesanan/refund POS, verifikasi setoran kasir tutup toko.
- **Pemetaan User Story Resmi**: US-3.1, US-9.1, US-9.2, US-38.1
- **SOP Eskalasi & Insiden**: Penanganan komplain kualitas makanan atau selisih setoran kas toko.

### 4.39 Peran #39: `cashier` — Kasir Point of Sale (POS)
- **Lini Bisnis / Domain**: F&B / Ritel (Resto)
- **Batas Otorisasi Finansial**: `Rp 2.000.000`
- **Izin Kunci (*Core Abilities*)**: `pos.checkout`, `cash.tender`, `qris.accept`
- **Rute Terotorisasi**: `GET /pos/menu`, `POST /pos/orders/create`, `POST /pos/payment`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Input pesanan menu POS, penerimaan pembayaran tunai/QRIS/kartu, cetak struk.
- **Pemetaan User Story Resmi**: US-2.1, US-9.3, US-9.4, US-38.2
- **SOP Eskalasi & Insiden**: Penyelesaian transaksi offline POS saat koneksi internet toko putus.

### 4.40 Peran #40: `kitchen_chef` — Kepala Dapur / Kitchen Leader
- **Lini Bisnis / Domain**: F&B / Cloud Kitchen
- **Batas Otorisasi Finansial**: `Rp 10.000.000`
- **Izin Kunci (*Core Abilities*)**: `kds.bump`, `recipe.costing`, `ingredient.spoilage`
- **Rute Terotorisasi**: `GET /kitchen/kds`, `POST /kitchen/kds/bump`, `POST /kitchen/spoilage`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penyelesaian antrean tiket pesanan KDS, pengawasan porsi resep HPP, kontrol bahan.
- **Pemetaan User Story Resmi**: US-7.1, US-8.1, US-74.1, US-74.2
- **SOP Eskalasi & Insiden**: Karantina bahan baku basi dan pengajuan permintaan cito ke dapur sentral.

### 4.41 Peran #41: `mall_leasing_manager` — Manajer Penyewaan Tenant Mall
- **Lini Bisnis / Domain**: Properti (Mall)
- **Batas Otorisasi Finansial**: `Rp 300.000.000`
- **Izin Kunci (*Core Abilities*)**: `tenant.lease_sign`, `turnover_rent.calc`, `unit.reserve`
- **Rute Terotorisasi**: `GET /mall/leasing/*`, `POST /mall/contract/sign`, `POST /mall/units/reserve`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penyusunan kontrak sewa unit mall, penetapan bagi hasil omzet (Turnover Rent).
- **Pemetaan User Story Resmi**: US-12.1, US-12.2, US-13.1, US-41.1
- **SOP Eskalasi & Insiden**: Penerbitan surat peringatan (SP) tunggakan sewa tenant mall.

### 4.42 Peran #42: `mall_meter_officer` — Petugas Pencatat Meter Utilitas
- **Lini Bisnis / Domain**: Properti (Mall)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `kwh.record`, `water.record`, `submeter.inspect`
- **Rute Terotorisasi**: `GET /mall/meters/*`, `POST /mall/meters/read`, `POST /mall/meters/inspect`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pencatatan angka meteran listrik kWh dan air bulanan tiap tenant mall.
- **Pemetaan User Story Resmi**: US-13.2, US-14.1, US-76.1
- **SOP Eskalasi & Insiden**: Deteksi dan pelaporan indikasi pencurian listrik atau kebocoran pipa tenant.

### 4.43 Peran #43: `bank_treasurer` — Bendahara Rekonsiliasi Bank
- **Lini Bisnis / Domain**: Fintech & Wealth (Fin)
- **Batas Otorisasi Finansial**: `Rp 2.000.000.000`
- **Izin Kunci (*Core Abilities*)**: `escrow.release`, `clearing.settle`, `va.match`
- **Rute Terotorisasi**: `GET /treasury/*`, `POST /treasury/escrow/release`, `POST /treasury/clearing/settle`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pelepasan dana escrow transaksi jual-beli mobil/B2B, rekonsiliasi kliring BI-FAST.
- **Pemetaan User Story Resmi**: US-1.4, US-2.2, US-48.3, US-50.2, US-83.1
- **SOP Eskalasi & Insiden**: Penahanan transfer dana terindikasi pola pencucian uang (AML red flag).

### 4.44 Peran #44: `wm_advisor` — Penasihat Keuangan Robo-Advisor
- **Lini Bisnis / Domain**: Fintech & Wealth (WM)
- **Batas Otorisasi Finansial**: `Rp 500.000.000`
- **Izin Kunci (*Core Abilities*)**: `portfolio.rebalance`, `kyc.approve`, `rwa.invest`
- **Rute Terotorisasi**: `GET /wealth/*`, `POST /wealth/rebalance`, `POST /wealth/kyc/approve`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Review usulan rebalancing portofolio robo-advisor nasabah prioritas, verifikasi KYC.
- **Pemetaan User Story Resmi**: US-71.2, US-73.2, US-73.3
- **SOP Eskalasi & Insiden**: Eskalasi anomali profil risiko nasabah dan toleransi drawdown portofolio.

### 4.45 Peran #45: `contract_lawyer` — Tim Legal & Manajer Kontrak
- **Lini Bisnis / Domain**: Legal (Contract)
- **Batas Otorisasi Finansial**: `Rp 1.000.000.000`
- **Izin Kunci (*Core Abilities*)**: `contract.draft`, `clause.sign`, `hash.seal`
- **Rute Terotorisasi**: `GET /contracts/*`, `POST /contracts/draft`, `POST /contracts/seal`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penyusunan draf kontrak hukum korporat, penyegelan hash kriptografi dokumen digital.
- **Pemetaan User Story Resmi**: US-28.2, US-29.2, US-45.1, US-84.2
- **SOP Eskalasi & Insiden**: Penerbitan surat penuntutan breach of contract dan sita jaminan.

### 4.46 Peran #46: `mfg_plant_manager` — General Manager Pabrik / Plant
- **Lini Bisnis / Domain**: Manufaktur (MFG)
- **Batas Otorisasi Finansial**: `Rp 1.000.000.000`
- **Izin Kunci (*Core Abilities*)**: `work_order.release`, `mrp.run`, `capacity.plan`
- **Rute Terotorisasi**: `GET /mfg/plant/*`, `POST /mfg/mrp/run`, `POST /mfg/workorder/release`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pelepasan jadwal produksi bulanan (MPS), review efisiensi OEE lini pabrik.
- **Pemetaan User Story Resmi**: US-35.1, US-36.1, US-37.1, US-40.2
- **SOP Eskalasi & Insiden**: Pemberhentian darurat lini pabrik saat terjadi kecelakaan kerja berat.

### 4.47 Peran #47: `mfg_line_operator` — Operator Lini Produksi Manufaktur
- **Lini Bisnis / Domain**: Manufaktur (MFG)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `bom.consume`, `step.complete`, `scrap.record`
- **Rute Terotorisasi**: `GET /mfg/operations/*`, `POST /mfg/bom/consume`, `POST /mfg/operation/complete`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pencatatan konsumsi bahan baku BOM di workstation, catat kuantitas produk jadi & scrap.
- **Pemetaan User Story Resmi**: US-37.2, US-37.3, US-38.3
- **SOP Eskalasi & Insiden**: Tombol andon berhenti lini jika komponen cacat melebihi batas toleransi.

### 4.48 Peran #48: `qms_inspector` — Inspektur Kendali Mutu (QC/QA)
- **Lini Bisnis / Domain**: Manufaktur (MFG)
- **Batas Otorisasi Finansial**: `Rp 100.000.000`
- **Izin Kunci (*Core Abilities*)**: `qc.pass`, `lot.quarantine`, `capa.open`
- **Rute Terotorisasi**: `GET /mfg/qms/*`, `POST /mfg/qc/pass`, `POST /mfg/qc/quarantine`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemeriksaan sampel mutu produk jadi/masuk, penerbitan status karantina lot cacat.
- **Pemetaan User Story Resmi**: US-39.1, US-39.2, US-39.3
- **SOP Eskalasi & Insiden**: Pemberitahuan CAPA (Corrective Action) kritis dan penahanan pengiriman gudang.

### 4.49 Peran #49: `agriculture_manager` — Manajer Kebun / Perkebunan
- **Lini Bisnis / Domain**: Agribisnis (Agri)
- **Batas Otorisasi Finansial**: `Rp 200.000.000`
- **Izin Kunci (*Core Abilities*)**: `harvest.schedule`, `fertilizer.budget`, `weather.react`
- **Rute Terotorisasi**: `GET /agri/estates/*`, `POST /agri/harvest/plan`, `POST /agri/budget`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Perencanaan masa tanam & panen kelapa sawit/padi, monitoring indeks vegetasi NDVI.
- **Pemetaan User Story Resmi**: US-62.1, US-62.2, US-86.1, US-86.2
- **SOP Eskalasi & Insiden**: Mitigasi bencana kekeringan atau serangan hama wereng skala blok kebun.

### 4.50 Peran #50: `estate_foreman` — Mandor Lapangan Perkebunan
- **Lini Bisnis / Domain**: Agribisnis (Agri)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `yield.measure`, `worker.attendance`, `pest.report`
- **Rute Terotorisasi**: `GET /agri/tasks`, `POST /agri/attendance`, `POST /agri/yield/record`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pencatatan absensi pemanen di lapangan, timbang tonase Tandan Buah Segar (TBS) harian.
- **Pemetaan User Story Resmi**: US-62.3, US-86.3
- **SOP Eskalasi & Insiden**: Laporan serangan hama mendadak pada tanaman plasma ke manajer kebun.

### 4.51 Peran #51: `fishery_captain` — Nakhoda Kapal Penangkap Ikan
- **Lini Bisnis / Domain**: Maritim (Fishery)
- **Batas Otorisasi Finansial**: `Rp 100.000.000`
- **Izin Kunci (*Core Abilities*)**: `catch.log`, `zone.vms`, `cold_hold.temperature`
- **Rute Terotorisasi**: `GET /fishery/vessel`, `POST /fishery/catch/log`, `POST /fishery/vms/ping`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pencatatan e-Logbook penangkapan tuna berlisensi, kontrol suhu palka beku -40°C.
- **Pemetaan User Story Resmi**: US-21.2, US-80.3
- **SOP Eskalasi & Insiden**: Sinyal marabahaya mayday laut dan pelaporan pelanggaran batas ZEE.

### 4.52 Peran #52: `telecom_engineer` — Insinyur Jaringan Menara Seluler
- **Lini Bisnis / Domain**: Infrastruktur (Telco)
- **Batas Otorisasi Finansial**: `Rp 150.000.000`
- **Izin Kunci (*Core Abilities*)**: `bts.power_adjust`, `fiber.splice`, `sla.monitor`
- **Rute Terotorisasi**: `GET /telco/bts/*`, `POST /telco/bts/power`, `POST /telco/dispatch`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemantauan alarm NOC menara BTS 4G/5G, koordinasi perbaikan kabel serat optik putus.
- **Pemetaan User Story Resmi**: US-65.1, US-67.1
- **SOP Eskalasi & Insiden**: Pemberangkatan genset mobile darurat saat pemadaman listrik gardu PLN.

### 4.53 Peran #53: `school_principal` — Kepala Sekolah / Rektor Kampus
- **Lini Bisnis / Domain**: Pendidikan (Edu)
- **Batas Otorisasi Finansial**: `Rp 100.000.000`
- **Izin Kunci (*Core Abilities*)**: `tuition.waive`, `curriculum.lock`, `degree.certify`
- **Rute Terotorisasi**: `GET /edu/admin/*`, `POST /edu/curriculum/lock`, `POST /edu/degree/certify`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penyegelan kurikulum akademik semester, pengesahan ijazah digital ber-hash chain.
- **Pemetaan User Story Resmi**: US-28.3, US-103.1
- **SOP Eskalasi & Insiden**: Penyelesaian skandal akademik atau sengketa beasiswa prestasi.

### 4.54 Peran #54: `academic_admin` — Staf Tata Usaha Akademik
- **Lini Bisnis / Domain**: Pendidikan (Edu)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `student.enroll`, `attendance.log`, `transcript.print`
- **Rute Terotorisasi**: `GET /edu/students/*`, `POST /edu/enroll`, `POST /edu/attendance`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pendaftaran mahasiswa baru, input transkrip nilai ujian, cetak kartu rencana studi.
- **Pemetaan User Story Resmi**: US-103.2
- **SOP Eskalasi & Insiden**: Verifikasi legalisir dokumen ijazah yang dicurigai palsu.

### 4.55 Peran #55: `waste_manager` — Manajer Pengolahan Limbah B3
- **Lini Bisnis / Domain**: Lingkungan (Waste)
- **Batas Otorisasi Finansial**: `Rp 75.000.000`
- **Izin Kunci (*Core Abilities*)**: `manifest.sign`, `incinerator.log`, `klhk.submit`
- **Rute Terotorisasi**: `GET /waste/manifests/*`, `POST /waste/incinerator/log`, `POST /waste/klhk/submit`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penerbitan manifes limbah medis infeksius Festronik KLHK, monitoring suhu insinerator.
- **Pemetaan User Story Resmi**: US-60.1, US-87.6
- **SOP Eskalasi & Insiden**: Prosedur penanganan tumpahan zat radioaktif atau limbah beracun B3.

### 4.56 Peran #56: `energy_auditor` — Auditor Efisiensi Energi & Karbon
- **Lini Bisnis / Domain**: Hijau & ESG (ESG)
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `scope1_2_3.calc`, `carbon_credit.verify`, `grid.audit`
- **Rute Terotorisasi**: `GET /esg/emissions/*`, `POST /esg/audit/verify`, `POST /esg/credits/retire`
- **Rute Terlarang Mutlak**: Semua rute mutating (`POST`, `PUT`, `DELETE`, `PATCH`). Auditor terikat prinsip read-only.
- **Rutinitas Operasional Harian**: Audit emisi karbon Scope 1, 2, 3 pabrik & mall, verifikasi klaim kredit karbon IDX.
- **Pemetaan User Story Resmi**: US-60.2, US-60.3, US-83.2
- **SOP Eskalasi & Insiden**: Penolakan klaim sertifikasi net-zero bila ditemukan manipulasi data solar.

### 4.57 Peran #57: `security_guard` — Petugas Satuan Pengamanan (Satpam)
- **Lini Bisnis / Domain**: Fasilitas & Keamanan
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `visitor.badge`, `cctv.monitor`, `gate_barrier.open`
- **Rute Terotorisasi**: `GET /security/gate`, `POST /security/visitor/checkin`, `POST /security/barrier/open`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pemeriksaan identitas tamu gedung/pabrik, pemindaian barang bawaan metal detector.
- **Pemetaan User Story Resmi**: US-14.2, US-57.1
- **SOP Eskalasi & Insiden**: Penguncian total fasilitas (lockdown) saat terjadi ancaman keamanan fisik.

### 4.58 Peran #58: `cleaning_staff` — Staf Kebersihan & Sanitasi
- **Lini Bisnis / Domain**: Fasilitas & Sanitasi
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `sanitation.checklist`, `biohazard_bin.empty`, `tag.ok`
- **Rute Terotorisasi**: `GET /facilities/checklist`, `POST /facilities/sanitation/sign`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Pembersihan disinfeksi ruang operasi RS & toilet mall, penempelan QR tag sanitasi.
- **Pemetaan User Story Resmi**: US-87.7
- **SOP Eskalasi & Insiden**: Pelaporan tumpahan cairan tubuh berbahaya untuk disterilisasi tim medis.

### 4.59 Peran #59: `customer_service` — Layanan Pelanggan & Helpdesk
- **Lini Bisnis / Domain**: Layanan Publik
- **Batas Otorisasi Finansial**: `Rp 1.000.000`
- **Izin Kunci (*Core Abilities*)**: `ticket.resolve`, `faq.answer`, `complaint.escalate`
- **Rute Terotorisasi**: `GET /support/tickets/*`, `POST /support/ticket/reply`, `POST /support/ticket/escalate`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Penerimaan keluhan pelanggan via obrolan multi-kanal, penanganan komplain pesanan.
- **Pemetaan User Story Resmi**: US-2.3, US-15.1, US-59.1
- **SOP Eskalasi & Insiden**: Eskalasi komplain viral di media sosial ke Public Relations tim krisis.

### 4.60 Peran #60: `it_support` — Staf Dukungan IT Lapangan
- **Lini Bisnis / Domain**: Teknologi Informasi
- **Batas Otorisasi Finansial**: `N/A`
- **Izin Kunci (*Core Abilities*)**: `terminal.reboot`, `printer.fix`, `offline_sync.force`
- **Rute Terotorisasi**: `GET /it/devices/*`, `POST /it/device/reboot`, `POST /it/sync/force`
- **Rute Terlarang**: Rute administrasi di luar domain tugas dan rute mutasi buku besar langsung tanpa kontrak.
- **Rutinitas Operasional Harian**: Troubleshooting printer kasir & barcode scanner, pemulihan sinkronisasi offline pos.
- **Pemetaan User Story Resmi**: US-65.2, US-102.2
- **SOP Eskalasi & Insiden**: Penggantian unit POS rusak cadangan swap dalam waktu < 10 menit.

---

## 5. Matriks Pemisahan Tugas (Segregation of Duties - SoD Matrix)

Untuk mencegah penipuan internal (*fraud*) dan kesalahan administratif, sistem memberlakukan larangan kepemilikan peran ganda secara bersamaan pada akun yang sama:

| Peran Inisiator (*Maker*) | Peran Penyetuju (*Checker*) | Alasan Larangan Perangkapan (Prinsip SoD) |
|---|---|---|
| `procurement_buyer` | `procurement_manager` | Mencegah penerbitan dan persetujuan PO sepihak tanpa verifikasi independen |
| `cashier` | `internal_auditor` | Mencegah kasir mengaudit rekonsiliasi kas laci miliknya sendiri |
| `doctor` | `pharmacist` | Mencegah peresepan dan pengeluaran obat narkotika/psikotropika oleh pihak yang sama |
| `mfg_line_operator` | `qms_inspector` | Mencegah pekerja lini meluluskan kontrol mutu barang hasil produksinya sendiri |
| `wms_picker` | `wms_supervisor` | Mencegah manipulasi stock opname dan kehilangan inventaris gudang |
| `venue_staff` | `venue_manager` | Mencegah override tiket komplemen pintu masuk tanpa audit pimpinan |
| `front_office` | `hotel_gm` | Mencegah pemberian upgrade kamar VIP komplemen fiktif |
| `mine_planner` | `royalty_officer` | Mencegah rekayasa perhitungan kadar bijih tambang untuk mengecilkan royalti negara |
| `bank_treasurer` | `holding_cfo` | Mencegah pelepasan transfer dana bernilai material tanpa *four-eyes approval* |
| `super_admin` | `internal_auditor` | Administrator sistem tidak boleh mengaudit tindakannya sendiri dalam log sistem |

---

## 6. Prosedur Audit Kepatuhan Akses & Pencabutan Kredensial Darurat

1. **Audit Kepatuhan Triwulanan**: Head of Compliance (`holding_compliance`) dan Lead Auditor (`internal_auditor`) meninjau seluruh akun aktif setiap 90 hari.
2. **Otomatisasi Non-Aktif**: Akun yang tidak login selama 30 hari otomatis dinonaktifkan (`status: suspended`).
3. **Pencabutan Akses Darurat (*Emergency Offboarding*)**: Dalam kasus pemutusan hubungan kerja sepihak atau indikasi kompromi kredensial:
   - Admin mengeksekusi `POST /admin/users/{id}/revoke-tokens` untuk mencabut seluruh Sanctum bearer token secara instan.
   - Sesi aktif di Redis diputus menggunakan `Redis::del('session:' . $userId)`.
   - Kartu akses fisik RFID (fasilitas, gudang, ruang server) dinonaktifkan di sistem keamanan gedung dalam waktu $< 60$ detik.
