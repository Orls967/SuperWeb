# Runbook Pemulihan Bencana & Prosedur Offline Folio Hotel

Runbook ini memandu tim IT Hotel, Front Office Manager, dan Night Auditor dalam mengatasi kondisi darurat kegagalan sistem, pemadaman konektivitas jaringan, atau bencana alam yang mempengaruhi sistem Property Management System (PMS) Superweb Hotel.

---

## 1. Tanggap Darurat Pemadaman Sistem (System Outage Response)

Jika sistem cloud PMS tidak dapat diakses atau terjadi kegagalan jaringan WAN:

1. **Aktivasi Modus Operasional Darurat (Emergency Downtime Mode)**:
   - Front Desk segera beralih ke formulir registrasi manual fisik dan buku tamu offline.
   - Papan tata graha menggunakan salinan fisik terakhir daftar kamar (*Housekeeping Morning Report*).
2. **Pencatatan Transaksi Tamu Offline**:
   - Seluruh pembebanan konsumsi outlet restoran, room service, dan minibar wajib ditandatangani manual oleh tamu menggunakan bon fisik 3 rangkap (*carbonized guest check voucher*).
   - Simpan seluruh bon fisik di folder pigeon-hole nomor kamar terkait.
3. **Penerbitan Kunci Kamar Darurat**:
   - Gunakan mesin encoder offline lokal yang terhubung dengan kartu master darurat (*emergency keycard programmer*).
   - Jika kunci kartu elektronik tidak merespons akibat gangguan kelistrikan, gunakan anak kunci mekanis master fisik di bawah pengawasan ketat Duty Manager dan Keamanan Hotel.

---

## 2. Prosedur Rekonsiliasi & Sinkronisasi Pasca-Pemulihan (Post-Outage Recovery)

Setelah konektivitas internet atau server PMS kembali pulih:

1. **Audit Integritas Data Pra-Input**:
   - Bandingkan waktu mulai pemadaman dan waktu pemulihan.
   - Kumpulkan seluruh berkas check-in, check-out, dan voucher outlet selama masa offline.
2. **Input Registrasi dan Folio Tertunda**:
   - Buat reservasi atau check-in walk-in yang terjadi selama masa offline secara berurutan sesuai jam kedatangan aktual.
   - Masukkan seluruh pembebanan bon manual ke dalam folio kamar aktif dengan keterangan `[OFFLINE DOWNTIME POSTING]`.
3. **Pemeriksaan Status Kamar dan Kunci IoT**:
   - Jalankan sinkronisasi massal status kamar (*bulk status refresh*) via API Gateway.
   - Verifikasi bahwa seluruh smart lock IoT menerima pembaruan masa berlaku kunci digital terbaru.
4. **Verifikasi Night Audit dan Keseimbangan Buku Besar**:
   - Jalankan audit integritas buku besar:
     ```bash
     php artisan hotel:audit --dry-run
     ```
   - Pastikan total saldo buku piutang tamu (`htl:guest_ledger:IDR`) seimbang dengan total mutasi fisik dan kasir perbankan.

---

## 3. Matriks Kontak Eskalasi & Rencana Kontinjensi

| Peran | Penanggung Jawab | Kanal Komunikasi | Tindakan Kunci |
| :--- | :--- | :--- | :--- |
| IT Systems Engineer | On-call Engineer | Hotline Darurat IT | Evaluasi koneksi WAN, restart local edge gateway, verifikasi integritas replika database lokal |
| Front Office Manager | FOM On-duty | Radio Channel 1 | Koordinasi alur antrian tamu, persetujuan batas kredit offline, otorisasi penerbitan master key |
| Financial Controller | Head of Finance | Telepon Internal Ext 104 | Pemantauan rekonsiliasi kasir fisik, validasi batch slip EDC manual, otorisasi jurnal penyesuaian |
| General Manager | GM / Resident Mgr | Ponsel Darurat | Keputusan aktivasi status darurat bencana, pengalihan tamu ke hotel mitra jika properti tidak aman |

---

## 4. Evaluasi Pasca-Insiden (Post-Mortem Review)

Setelah pemulihan penuh selesai:
- Tim IT dan Manajemen Hotel mengadakan rapat *post-mortem* maksimal 48 jam pasca-insiden.
- Laporan tertulis mendokumentasikan akar masalah (*root cause*), durasi dampak (*downtime duration*), total nilai transaksi offline yang direkonsiliasi, dan rencana tindakan mitigasi preventif.
