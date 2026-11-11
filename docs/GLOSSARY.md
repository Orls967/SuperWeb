# Glosarium SuperWeb (GLOSSARY.md)

Glosarium istilah baku arsitektur dan bisnis yang digunakan lintas modul SuperWeb.

---

## Istilah Design System & Aksesibilitas (Fase 240)

- **Luminansi Relatif ($L$):** Kecerahan relatif dari sembarang titik dalam ruang warna sRGB, dinormalisasi ke 0 untuk hitam paling gelap dan 1 untuk putih paling terang, dihitung menurut formula WCAG 2.1 ($0.2126 \times R + 0.7152 \times G + 0.0722 \times B$) dengan kanal terlinearisasi. Diimplementasikan dalam integer parts-per-million (ppm).
- **Rasio Kontras (Contrast Ratio):** Perbandingan luminansi relatif antara teks/elemen antarmuka dengan latar belakangnya, $(L_1 + 0.05) / (L_2 + 0.05)$ di mana $L_1$ adalah warna yang lebih terang. Diukur dalam basis points (bps) di mana rasio 4.5:1 setara dengan 45.000 bps.
- **Token Desain (Design Token):** Nilai atomik terpusat (warna, tipografi, spasi, radius, elevasi) yang diabstraksikan dari nama semantiknya (misal: `bg`, `text`, `primary`, `onPrimary`, `muted`, `danger`) untuk mendukung persistensi multi-tema.
- **Pewarisan Tema Tri-State:** Mekanisme hierarki resolusi token di mana tema kontras tinggi (`hc`) mewarisi definisi tema gelap (`dark`), dan tema gelap mewarisi tema terang (`light`).
- **Alias Token:** Referensi token ke nama token lain dalam sintaks `{token_name}`, yang wajib diselesaikan dalam ruang lingkup tema peminta.
- **Siklus Token (Token Cycle):** Kesalahan konfigurasi token di mana alias saling merujuk secara melingkar ($a \rightarrow b \rightarrow a$), ditandai dengan kode `TOKEN_CYCLE`.
- **Komponen Kanonikal (`x-ds.*`):** Komponen Blade standar perusahaan yang dikembangkan dan dirilis secara terpusat untuk dipakai lintas 30 lini bisnis.
- **ComponentRelease State Machine:** Mesin status 7-tahap (`proposed`, `in_design`, `a11y_review`, `approved`, `released`, `deprecated`, `removed`) yang mengatur siklus hidup komponen desain.
- **Linter Aksesibilitas (`ui:lint`):** Perangkat linter kode statis dan DOM render yang memverifikasi kepatuhan terhadap aturan aksesibilitas (WCAG 2.1) dan mendeteksi warna hard-code.
- **Task Success Rate (Bps):** Metrik keberhasilan uji keterpakaian dalam riset UX, diukur dalam basis points ($0 \dots 10.000$).
- **Responsive Card Transformation:** Transformasi responsif otomatis pada komponen tabel `x-ds.table` di mana pada layar sempit ($\le 375$ px) baris tabel berubah menjadi kartu vertikal tanpa scroll horizontal.
- **Parts-Per-Million (ppm):** Satuan integer presisi sejuta ($1/1.000.000$) yang dipakai untuk menyatakan nilai saluran warna sRGB terlinearisasi ($0 \dots 1.000.000$) guna menghindari drift floating point antar arsitektur komputasi.
- **Basis Points (bps):** Satuan nilai rasio dalam kelipatan $1/10.000$ (100 bps = 1%). Dipakai untuk rasio kontras warna ($4.5:1 = 45.000$ bps) dan tingkat keberhasilan tugas riset UX ($92\% = 9.200$ bps).
- **Focus Trap:** Mekanisme penahanan penelusuran keyboard di dalam jendela modal interaktif sehingga tombol `Tab` tidak dapat menggeser fokus ke elemen di belakang overlay modal sampai modal ditutup.
- **Accessible Name (Nama Aksesibel):** Teks yang diasosiasikan oleh browser dengan elemen antarmuka pengguna (tombol, input, link) melalui teks konten, atribut `aria-label`, `aria-labelledby`, atau `<label for>`, yang dibacakan oleh pembaca layar.
- **Linearisasi sRGB:** Transformasi matematis non-linear kanal warna sRGB ke ruang luminansi linear menggunakan kurva daya gamma $2.4$, yang dibulatkan ke bilangan bulat ppm.
- **Double-Submit Prevention:** Penguncian tombol aksi formulir seketika setelah interaksi pertama pengguna untuk mencegah transaksi duplikat akibat penekanan berulang tombol kirim.
- **Tabular Nums (`tabular-nums`):** Properti font CSS `font-variant-numeric: tabular-nums` yang menyamakan lebar karakter angka numerik agar perataan kolom angka pada tabel berderet rapi secara vertikal.
- **Ratchet Baseline:** Mekanisme kontrol mutu di mana ambang batas pelanggaran hanya diizinkan berkurang atau tetap seiring waktu, dan dilarang bertambah pada commit baru.
- **Two-Source Audit:** Validasi silang independen antara dua representasi data yang terpisah (misal status relasional aktif vs riwayat append-only dan pemindaian filesystem) untuk mendeteksi korupsi data tanpa rekonsiliasi manual.
- **Component Usage Scanner (`ds:usage-scan`):** Alat pemindai statis berbasis ekspresi reguler yang memetakan pemanggilan komponen Blade kanonikal pada berkas view aplikasi untuk memastikan komponen yang dideprecate tidak lagi dipanggil sebelum dihapus.
- **Theme Switcher:** Komponen interaktif antarmuka yang memungkinkan pengguna beralih antara tema Terang, Gelap, dan Kontras Tinggi tanpa reload halaman penuh melalui manipulasi atribut `data-theme` pada tag `<html>`.
- **Contrast Badge:** Lencana visual yang menampilkan nilai numerik rasio kontras warna aktual beserta label status kelulusan WCAG AA/AAA secara langsung pada katalog komponen.
- **SvgChartGeometry:** Utilitas komputasi backend berbasis PHP untuk memetakan dataset numerik ke koordinat piksel vektor SVG (titik, garis, batang, kurva) tanpa pustaka charting JavaScript eksternal.
- **TableQueryState:** Objek status pemanggilan tabel di lapisan PHP yang mengurai, memvalidasi, dan mensanitasi parameter query string (sort kolom, arah pengurutan, filter teks, dan halaman pagination) untuk mencegah injeksi kolom atau SQL tidak valid.
- **StatusBadgeRegistry:** Peta registri terpusat yang menghubungkan konstanta `STATUS_*` dari berbagai State Machine aplikasi ke varian warna lencana desain semantik (`success`, `warning`, `danger`, `info`, `neutral`).
- **IdFormatter:** Format utilitas lokalisasi standar Indonesia untuk penyajian bilangan bulat moneter Rupiah, format tanggal `dd/mm/yyyy`, dan angka tabular dengan zona waktu pengguna.
- **A11yHtmlLinter:** Mesin penganalisis pohon DOM dokumen HTML untuk memverifikasi atribut gambar, label form input, teks tombol, dan urutan fokus keyboard secara statis.
- **UsabilityTestFinding:** Catatan hasil observasi langsung pada uji coba antarmuka oleh staf operasional yang memuat tingkat keparahan kendala (`low`, `medium`, `high`, `critical`) dan status remediasi backlog.
- **ComponentReleaseAction:** Kelas layanan aplikasi yang mengorkestrasi transisi status siklus hidup komponen desain dengan memeriksa seluruh guard aturan bisnis sebelum mengubah basis data.
- **SparklinePath:** Jalur koordinat string SVG `<path d="...">` yang dihitung secara deterministik dari deret waktu nilai numerik untuk visualisasi tren mini.
- **ComponentReleaseGuard:** Logika penjaga yang mengaudit kondisi prasyarat sebelum mengizinkan transisi status mesin rilis komponen.
- **DsAuditCorruptionFixture:** Data tiruan yang disengaja dirusak untuk menguji keandalan deteksi audit dua sumber `ds:audit`.
- **A11yFindingSeverity:** Klasifikasi tingkat urgensi perbaikan aksesibilitas mulai dari `low`, `medium`, `high`, hingga `critical`.
- **Inverted Index (Indeks Terbalik):** Struktur data pemetaan kata/istilah (terms) ke daftar identitas dokumen beserta bobot posisi dan frekuensi pemunculan untuk mempercepat operasi pencarian teks penuh di lapisan basis data relasional.
- **Okapi BM25:** Fungsi pembobotan peringkat dokumen pencarian berbasis probabilitas informasi yang mempertimbangkan saturasi frekuensi istilah ($k_1$) dan normalisasi panjang dokumen ($b$).
- **Trigram Jaccard Similarity:** Koefisien kesamaan statistik antara himpunan fragmen sub-kata 3 karakter (trigram) dari kueri pencarian terhadap istilah dalam indeks, digunakan untuk koreksi otomatis saltik (typo tolerance).
- **Policy-First Search Filtering:** Pola desain keamanan pencarian di mana filter hak akses pengguna dan batasan lingkup organisasi (RBAC/ABAC) diterapkan sebelum proses perankingan hasil, menjamin nihilnya kebocoran informasi sensitif.
- **Kotak Masuk Tugas Saya (§19.1):** Dasbor terpadu yang menggabungkan seluruh antrean penugasan, persetujuan transaksi finansial, tiket operasional, dan eskalasi wewenang lintas modul dalam satu antarmuka kerja tunggal.
- **Search Diversity Ranker:** Algoritma perankingan sekunder yang menerapkan diskon relevansi marginal pada dokumen dari modul atau kategori yang sama untuk menghindari monopoli satu jenis entitas pada halaman pertama hasil pencarian.
- **SLA Breach Threshold:** Batas waktu maksimum yang diizinkan untuk pemrosesan sebuah tugas sebelum mekanisme eskalasi berjenjang (Level 1 $\to$ Level 4) terpicu secara otomatis.
- **Search Pinning:** Kemampuan personalisasi yang memungkinkan pengguna menyematkan dokumen atau hasil pencarian penting ke posisi teratas kueri rujukan mereka.

---

## Istilah Batas Modul & Kepemilikan Data (Fase R4 / W8-P4)

- **Directed Acyclic Graph (DAG) Modul:** Graf dependensi berarah antar direktori modul (`modules/*`) tanpa siklus berpanjang $\ge 2$, menegakkan *Acyclic Dependencies Principle* (Robert C. Martin, ADR-0041).
- **Strongly Connected Components (Tarjan SCC):** Partisi maksimal graf dependensi berarah ke dalam subgraf berhubung kuat dalam $\mathcal{O}(V + E)$; komponen beranggota $|C| > 1$ menandakan siklus terlarang antar modul (self-loop $|C|=1$ diabaikan per W8-D41).
- **Longest Prefix Match (`TablePrefixOwnershipResolver`):** Aturan resolusi kepemilikan tabel basis data yang memilih prefiks terpanjang di `config/modules.php` (mis. `hcm_pay_` mengalahkan `hcm_`); prefiks kembar lintas dua modul memicu status `PREFIX_CONFLICT`.
- **Contract Coverage Scorer (Permil):** Skor kepatuhan modular bilangan bulat $0\text{--}1.000\text{ permil}$ ($\lfloor 1.000 \times C_{\text{contract}} / (C_{\text{contract}} + V_{\text{direct}}) \rfloor$) tanpa aritmetika titik mengambang (total $0 \to 1.000$).
- **BoundaryViolation State Machine:** Mesin status 7 tahap (`detected`, `triaged`, `contract_designed`, `migrating`, `verified`, `closed`, `waived`) dengan riwayat transisi append-only (`core_boundary_violation_transitions`) dan guard wajib hapus entri baseline sebelum `verified`.
- **Waiver Arsitektur Ber-Anchor:** Pengecualian sementara pelanggaran arsitektur yang wajib mencantumkan anchor `docs/DECISIONS.md#...`, alasan, pemilik, dan kedaluwarsa $\le 90$ hari (otomatis dikembalikan ke `triaged` lewat `boundary:expire-waivers`).

