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
