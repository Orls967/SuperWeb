# Konsep Arsitektur: Mesin Pencarian Global & Navigasi Enterprise 30 Lini

**Fase:** 194 — Platform: Scope-Aware Global Search & Enterprise Navigation  
**Klasifikasi:** Fondasi Platform Kernel (Core & Shared)  
**Terkait:** §5 dan §19.1 Rencana Gelombang 8

---

## 1. Latar Belakang & Filosofi Desain

SuperWeb bertransformasi dari sistem manajemen terisolasi menjadi platform operasi bisnis enterprise yang mencakup 30 lini industri vertikal. Setiap lini mengelola domain entitas terspesialisasi:
- **Otomotif & Bengkel (AutoServe & AutoDex):** Work order perbaikan, riwayat servis kendaraan, nomor rangka (VIN), pelat nomor polisi, dan suku cadang bengkel.
- **Rantai Pasok & Transportasi (Logistics & Marine):** Surat jalan pengiriman barang, resi kargo, jadwal kontainer pelabuhan, manifes kapal, dan perizinan berlayar.
- **Tata Kelola Hukum & Korporat (Contract & Governance):** Akta notaris, perjanjian kerja sama (PKS), addendum kontrak vendor, dan sertifikasi kepatuhan regulasi.
- **Fasilitas & Infrastruktur (Asset & Maintenance):** Aset fisik pabrik, sertifikasi kelaikan mesin, dan riwayat pemeliharaan berkala.
- **Entitas Mitra & Pihak Ketiga (Party & CRM):** Direktori vendor terdaftar, profil pelanggan korporat, dan data kontak terverifikasi.

Pencarian global dalam skala multi-sektor ini tidak dapat diselesaikan dengan pencarian teks biasa (`LIKE '%keyword%'`). Pendekatan tersebut memiliki tiga kelemahan fatal:
1. **Penurunan Kinerja Skalabilitas Eksponensial:** Pemindaian tabel penuh (*full table scan*) pada jutaan baris di puluhan tabel relasional melumpuhkan basis data operasional.
2. **Ketiadaan Relevansi Term:** Hasil pencarian tidak memiliki bobot pembeda antara entitas yang memiliki kemunculan kata kunci relevan pada judul dokumen vs kemunculan sekunder pada catatan kaki.
3. **Risiko Kebocoran Informasi Antar-Penyewa dan Antar-Peran (IDOR):** Pencarian yang tidak memfilter hak akses pada lapisan terdepan dapat mengekspos eksistensi dokumen rahasia kepada pihak yang tidak berhak.

---

## 2. Struktur Indeks Teks Penuh Terbalik (Inverted Search Index)

Arsitektur Fase 194 menggunakan struktur tabel terpusat `core_search_indices` yang bertindak sebagai indeks terbalik lokal.

### Karakteristik Desain:
1. **Indeks Terdesentralisasi Berbasis Modul:** Masing-masing modul mendaftarkan penyedia pencarian (`SearchProviderInterface`) yang mengekstrak data dari model domain ke dalam representasi dokumen datar (*flattened search document*).
2. **Tokenisasi Teks Deterministik:** Teks dokumen dipecah menjadi token leksikal (kata), dinormalisasi ke huruf kecil, dibersihkan dari tanda baca, dan disaring dari kata henti umum (*stopwords* bahasa Indonesia dan Inggris).
3. **Statistik Korpus Lokal:** Mesin menyimpan parameter global jumlah dokumen ($N$), panjang rata-rata dokumen ($\text{avgdl}$), dan frekuensi dokumen per term ($n(q)$) untuk digunakan dalam kalkulasi BM25.

---

## 3. Formulasi Relevansi Probabilistik BM25

Relevansi dihitung menggunakan algoritma BM25 kanonikal Robertson et al. (2009):

$$\text{IDF}(q_i) = \ln \left( 1 + \frac{N - n(q_i) + 0.5}{n(q_i) + 0.5} \right)$$

$$\text{Weight}(q_i, D) = \text{IDF}(q_i) \cdot \frac{f(q_i, D) \cdot (k_1 + 1)}{f(q_i, D) + k_1 \cdot \left( 1 - b + b \cdot \frac{|D|}{\text{avgdl}} \right)}$$

$$\text{Score}_{\text{BM25}}(D, Q) = \sum_{i=1}^{n} \text{Weight}(q_i, D)$$

### Parameter Operasional:
- $k_1 = 1.2$: Mengontrol saturasi frekuensi istilah dalam dokumen. Peningkatan frekuensi istilah melebihi titik jenuh hanya memberikan kontribusi marginal tambahan, mencegah teknik manipulasi kata kunci (*keyword stuffing*).
- $b = 0.75$: Mengontrol derajat penalti panjang dokumen. Dokumen yang sangat panjang dipenalti secara proporsional kecuali istilah yang dicari muncul dengan densitas yang cukup tinggi.

---

## 4. Toleransi Saltik dengan Trigram Jaccard

Pengguna sistem bergerak di lapangan (misal mekanik bengkel atau petugas pelabuhan) sering mengalami kesalahan pengetikan pada perangkat mobile. Mesin pencari mengintegrasikan algoritma A5 `TrigramJaccardMatcher`:

1. **Pembuatan Trigram Berkarakter Sentry:**  
   String kueri input diawali dan diakhiri dengan sentry `$$` agar karakter awal dan akhir kata terwakili dalam token 3-karakter.  
   Misal: `"kargo"` $\rightarrow$ `["$$k", "$ka", "kar", "arg", "rgo", "go$", "o$$"]`.
2. **Evaluasi Koefisien Jaccard:**  
   $$J(A, B) = \frac{|A \cap B|}{|A \cup B|}$$
3. **Penyusunan Saran Alternatif:**  
   Bila kueri pengguna menghasilkan skor BM25 nol, mesin memindai leksikon istilah terdaftar. Istilah dengan $J \ge 0.35$ disajikan sebagai saran leksikal alternatif (*did-you-mean suggestion*).

---

## 5. Keamanan Policy-First & Perlindungan IDOR

Keamanan data adalah prinsip mutlak yang tidak dapat dikompromikan. Algoritma A6 `PolicyFirstRanker` menjamin prinsip zero-trust:
- Dokumen memuat atribut `allowed_roles` (daftar JSON peran yang berhak membaca) dan `tenant_id`.
- Saat kueri pencarian dieksekusi, sistem mengambil peran aktif (`user->role`) dan penyewa aktif pengguna.
- Evaluasi kebijakan izin dijalankan pada langkah pertama pemfilteran:
  $$\text{IsPermitted} = (\text{UserRole} \in \text{AllowedRoles}) \lor (\text{UserRole} = \text{'admin'})$$
  $$\text{IsTenantMatch} = (\text{DocumentTenant} = \text{UserTenant}) \lor (\text{DocumentTenant} = \text{'GLOBAL'})$$
- Dokumen yang gagal pada salah satu dari dua kondisi di atas secara instan dieliminasi dari memori sebelum kalkulasi BM25 dijalankan.
- Tidak ada kebocoran metadata berupa jumlah hasil pencarian atau judul dokumen yang tidak diizinkan.

