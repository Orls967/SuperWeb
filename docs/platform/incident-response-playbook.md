# Playbook Penanganan Insiden Platform & Keandalan Layanan

## 1. Klasifikasi Tingkat Keparahan Insiden (Severity Levels)

Setiap gangguan pada ekosistem platform diklasifikasikan ke dalam 4 tingkatan:

- **SEV-1 (Kritis / Outage Menyeluruh)**:
  - Definisi: Layanan inti (API Gateway, Otentikasi, atau Pemrosesan Transaksi) tidak dapat diakses sama sekali oleh lebih dari 50% tenant.
  - Dampak Error Budget: Konsumsi melebihi 20% budget dalam 1 jam (Burn Rate $\ge 14.4$).
  - Target Response Time: $\le 5$ menit.
  - Target Resolusi (TTR): $\le 30$ menit.
- **SEV-2 (Mayor / Penurunan Kualitas Signifikan)**:
  - Definisi: Fitur sekunder mengalami gangguan (misal: keterlambatan pelaporan, provisioning sandbox gagal, latensi $\ge 2$ detik).
  - Target Response Time: $\le 15$ menit.
  - Target Resolusi: $\le 2$ jam.
- **SEV-3 (Moderat / Minor)**:
  - Definisi: Anomali parsial tanpa dampak langsung pada operasional pengguna harian.
  - Target Response Time: $\le 1$ jam.
  - Target Resolusi: $\le 24$ jam.
- **SEV-4 (Kosmetik / Pertanyaan DX)**:
  - Definisi: Ketidaksempurnaan visual dashboard dev portal atau perbaikan dokumentasi internal.

---

## 2. Alur Eskalasi dan Peran Komando Insiden

1. **Incident Commander (IC)**:
   - Mengambil kendali komunikasi dan koordinasi tim investigasi.
   - Mengambil keputusan pembekuan deployment (freeze) jika error budget habis.
2. **Operations Lead**:
   - Menganalisis metrik SLO, log korelasi trace, dan kesehatan infrastruktur database.
   - Menjalankan rollback instan ke commit/snapshot stabil jika diperlukan.
3. **Communications Lead**:
   - Memperbarui status page dan mengirimkan notifikasi internal kepada stakeholder.

---

## 3. Prosedur Pasca-Insiden (Postmortem Protocol)

Dalam waktu maksimal $2 \times 24$ jam setelah insiden SEV-1 atau SEV-2 dinyatakan pulih:
1. Rekam dokumen postmortem formal pada tabel `plt_postmortems` menggunakan form dev portal atau artisan CLI.
2. Identifikasi 5-Whys root cause analysis tanpa menyalahkan individu (blameless postmortem culture).
3. Daftarkan minimal 2 tindakan preventif terukur (action items) yang memiliki tiket pelacakan dan batas waktu implementasi.

---

## 4. Pelacakan SLA & Integrasi Dashboard Observabilitas

- Setiap insiden terhubung secara otomatis ke catatan observabilitas `plt_service_slos` melalui kolom korelasi `service_name`.
- Penghitungan MTTR (Mean Time to Resolution) dan MTBF (Mean Time Between Failures) diperbarui secara real-time pada ViewModel `SloMonitorViewModel`.
- Stakeholder bisnis dan pimpinan engineering menerima ringkasan otomatis setiap penutupan tiket insiden berskala SEV-1 dan SEV-2.

