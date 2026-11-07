# Spesifikasi Arsitektur Sistem Crowd Safety AI & Biometrik — Fase 117

## 1. Komponen Subsistem
1. **Turnstile Hardware Abstraction Layer (HAL):**
   - Menghubungkan gerbang fisik (turnstile tripod, flap barrier, sliding gate) dengan antarmuka digital venue.
   - Mendukung mode darurat *fail-open* di mana turnstile secara mekanis melepaskan penguncian saat menerima sinyal `EMERGENCY_EVACUATION_TRIGGERED`.
2. **LiDAR & Camera Optical Density Aggregator:**
   - Membaca metadata deteksi orang (headcount bounding boxes) tanpa menyimpan gambar video.
   - Mengirimkan payload hitungan headcount per zona dengan interval polling 5 detik ke basis data `ven_crowd_telemetries`.
3. **Emergency Dispatch Bus:**
   - Menghubungkan modul Venue dengan modul Security, Logistics, dan Healthcare ketika status insiden kerumunan meningkat.

## 2. Invarian Operasional Gerbang & Kerumunan
- **Invarian 1 (Zero Gate Stagnation):** Jika pintu utama dibatasi (`GATE_RESTRICTED`), gerbang alternatif atau pintu keluar darurat sekunder wajib berstatus siap pakai dalam waktu $\le 30$ detik.
- **Invarian 2 (Single Active Biometric Token):** Setiap pengguna hanya boleh memiliki satu templat hash biometrik berstatus `ENROLLED_ACTIVE` pada waktu yang sama. Pendaftaran baru otomatis mencabut kredensial lama.
- **Invarian 3 (Conservation of Headcount):** Total orang terhitung di seluruh zona venue tidak boleh melebihi total tiket masuk yang terscan valid:
  $$\sum_{z \in \text{Zones}} \text{Headcount}(z) \le \text{Total Valid Entries} - \text{Total Valid Exits} + \epsilon_{\text{staff}}$$

