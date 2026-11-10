# Spesifikasi Teknis: Digital Twin State Bus (Fase 67 & R8)

Dokumen ini mendokumentasikan spesifikasi teknis mendalam arsitektur **Digital Twin State Bus** pada platform Superweb, mencakup model data append-only, struktur rantai hash kriptografis, deduplikasi state identik, persistensi snapshot berkala, dan mekanisme rekonstruksi waktu (*time-travel state reconstruction*).

---

## 1. Definisi & Konsep Dasar

Digital Twin State Bus adalah subsistem terdesentralisasi yang bertugas mencatat dan memproyeksikan bayangan digital (*digital shadow / twin*) dari setiap entitas bisnis riil pada platform Superweb. Entitas-entitas ini mencakup:
- **Armada Kendaraan (`vehicle`):** Mencatat odometer, status pemeliharaan, kondisi baterai/bahan bakar, dan lokasi depot.
- **Episode Rawat Inap (`hospital_episode`):** Mencatat tanggal masuk, utilisasi tempat tidur, diagnosis, dan status kepulangan.
- **Kamar Hotel (`hotel_room`):** Mencatat status keterisian, tarif harian yang diakrualkan, dan jadwal audit malam.
- **Peti Kemas Pelabuhan (`container`):** Mencatat waktu kedatangan di dermaga, masa bebas (*free time*), dan akumulasi denda demurrage.

### Perbedaan State Bus vs Event Spine:
- **Event Spine (Fase R8):** Mengalirkan notifikasi peristiwa transien (*fire-and-forget* atau *at-least-once delivery*) antar proses mikro-layanan (misal: "Faktur Diterbitkan", "Pembayaran Diterima").
- **Digital Twin Bus (Fase 67):** Mengabadikan evolusi keadaan (*state evolution*) entitas secara persisten, membentuk rantai hash matematis yang membuktikan bahwa state entitas saat ini merupakan hasil sah dari seluruh transisi masa lalunya.

---

## 2. Struktur Rantai Hash Kriptografis

Setiap entitas twin memiliki rantai hash tersendiri yang independen dari entitas lain. Rantai ini disimpan di tabel `sim_twin_states`.

### Struktur Skema Basis Data:
- `id`: Identifier log auto-increment (`bigint unsigned`).
- `entity_type`: String pengenal modul/domain (misal: `vehicle`, `hospital_episode`).
- `entity_id`: String UUID atau ID unik entitas bisnis.
- `event_hash`: String heksadesimal 64 karakter SHA-256.
- `prev_hash`: String heksadesimal 64 karakter hash rekaman sebelumnya (atau `null` untuk state genesis).
- `is_sandbox`: Boolean pembeda apakah state dicatat dalam mode simulasi sandbox atau live.
- `state_payload`: Objek JSON yang menyimpan atribut keadaan saat ini.
- `created_at`: Timestamp penanggalan rekaman.

### Rumus Perhitungan Hash Transisi:
1. **Keadaan Awal (Genesis State):**
   $$\text{event\_hash}_1 = \text{SHA256}(\text{"null\|"} \cdot \text{canonicalJson}(\text{payload}_1))$$
2. **Keadaan Berikutnya ($k \ge 2$):**
   $$\text{event\_hash}_k = \text{SHA256}(\text{event\_hash}_{k-1} \cdot \text{"\|"} \cdot \text{canonicalJson}(\text{payload}_k))$$

Aturan kanonikalisasi JSON:
- Kunci asosiatif diurutkan leksikografis menggunakan `ksort()`.
- Encoding menggunakan `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.

---

## 3. Aturan Deduplikasi State Identik

Salah satu tantangan utama pada Digital Twin Bus adalah frekuensi pembaruan tinggi yang tidak selalu mengubah nilai atribut (misal sensor GPS mengirimkan koordinat yang sama saat kendaraan parkir).

Untuk mencegah pemborosan ruang penyimpanan basis data, `DigitalTwinService::record()` memberlakukan aturan deduplikasi otomatis:
1. Sistem mengambil rekaman terakhir untuk pasangan `(entity_type, entity_id)` yang diminta.
2. Jika rekaman sebelumnya ada dan payload baru menghasilkan hash transisi yang identik dengan `event_hash` aktif:
   $$\text{new\_hash} == \text{current\_latest\_hash}$$
3. Sistem **tidak melakukan penulisan baris baru** ke tabel `sim_twin_states`. Operasi dianggap sukses dan mengembalikan instance rekaman yang ada.

Dengan aturan ini, ribuan tick simulasi yang tidak mengubah kondisi fisik entitas tidak akan membebani basis data.

---

## 4. Snapshot Periodik & Kompresi Delta

Untuk mendukung query performa tinggi pada entitas dengan puluhan ribu rekaman transisi, subsistem snapshot periodik mengambil gambar keadaan (*snapshot*) pada tabel `sim_twin_snapshots` setiap interval milestone (misal hari ke-7, ke-30, ke-90).

### Skema Tabel `sim_twin_snapshots`:
- `run_id`: UUID sesi simulasi pengambil snapshot (opsional).
- `day_number`: Hari virtual saat snapshot direkam.
- `entity_type`, `entity_id`: Kunci gabungan identitas entitas.
- `state_hash`: Hash integritas snapshot.
- `state_payload`: Data JSON keadaan penuh pada saat snapshot.
- Unique Index: `['run_id', 'day_number', 'entity_type', 'entity_id']`.

### Analisis Kompresi Delta (`TwinSnapshotArchiveService`):
Operator dapat menganalisis perbedaan keadaan antara dua hari snapshot melalui `computeDelta()`:
- `added_keys`: Atribut yang baru muncul pada hari berjalan.
- `removed_keys`: Atribut yang dihapus dari state.
- `modified_keys`: Atribut yang nilainya berubah (memuat nilai awal dan nilai akhir).
- `identical`: Boolean penanda apakah keadaan sama persis.

---

## 5. Reka Ulang Waktu Lampau (Time-Travel Reconstruction)

Kemampuan *time-travel debugging* memungkinkan operator merekonstruksi keadaan persis suatu entitas pada hari virtual tertentu $T_{\text{target}}$:
1. Cari snapshot terdekat yang memiliki $\text{day\_number} \le T_{\text{target}}$.
2. Muat payload snapshot sebagai basis awal keadaan entitas.
3. Ambil seluruh baris transisi dari `sim_twin_states` yang bertanggal setelah snapshot tersebut hingga batas waktu $T_{\text{target}}$.
4. Terapkan pembaruan secara berurutan (*fold / replay*):
   $$\text{State}(T) = \text{merge}(\text{State}_{\text{base}}, \Delta_1, \Delta_2, \dots, \Delta_m)$$
5. Hasil rekonstruksi disegel dengan hash verifikasi kriptografis untuk membuktikan keabsahan representasi.

---

## 6. Prosedur Audit Integritas Rantai Twin

Integritas Digital Twin diverifikasi menggunakan metode `DigitalTwinService::verifyChain(string $type, string $id)`:
1. Ambil seluruh rekaman untuk entitas terurut secara menaik (`id ASC`).
2. Periksa apakah setiap baris memiliki `prev_hash` yang persis sama dengan `event_hash` baris sebelumnya.
3. Evaluasi ulang hash kanonikal dari `state_payload` dan bandingkan dengan `event_hash` yang tersimpan.
4. Jika ditemukan diskrepansi sedikit pun, metode mengembalikan `false` dan mencatat ID baris yang mengalami korupsi data.

