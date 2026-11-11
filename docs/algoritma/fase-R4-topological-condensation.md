# Spesifikasi Algoritma — Pengurutan Topologis & Kondensasi Stratifikasi Modul

## 1. Konteks Teoretis
Untuk memastikan kepatuhan terhadap prinsip arsitektur berlapis (*layered architectural hierarchy*, K-07), platform SuperWeb memerlukan penataan urutan topologis linier dan pengelompokan strata modul (*architectural strata/layers*).

Algoritma `DependencyCondensationGraph` mengimplementasikan algoritma deterministik Kahn (1962) untuk pengurutan topologis, penentuan strata kedalaman struktural, serta perhitungan jalur kritis (*longest dependency path*).

---

## 2. Formulasi Matematika

### 2.1 Pengurutan Topologis (Kahn's Algorithm)
Diberikan graf ketergantungan terarah $G = (V, E)$:
1. Hitung derajat masuk (*in-degree*) $\deg^-(v)$ dan derajat keluar (*out-degree*) $\deg^+(v)$ untuk setiap modul $v \in V$:
   $$\deg^-(v) = |\{u \in V \mid (u, v) \in E\}|$$
   $$\deg^+(v) = |\{w \in V \mid (v, w) \in E\}|$$
2. Kumpulkan simpul akar (*root modules*) dengan $\deg^-(v) = 0$, diurutkan secara alfabetis.
3. Hapus simpul akar terkecil leksikografis, kurangi derajat masuk tetangganya, dan masukkan simpul baru dengan derajat masuk 0 ke antrean.
4. Ulangi hingga semua simpul diproses. Jika $|L| = |V|$, graf terbukti merupakan Directed Acyclic Graph (DAG) bebas siklus.

### 2.2 Penentuan Strata Lapisan (Architectural Strata)
Modul dibagi menjadi lapisan-lapisan hierarki diskret:
- **Lapisan 0 (Kernel / Sinks)**: Modul dengan $\deg^+(v) = 0$ (tidak memiliki dependensi keluar ke modul lain, misal kernel Shared).
- **Lapisan $k + 1$**: Modul yang bergantung pada modul pada lapisan maksimum $k$:
  $$\text{Layer}(u) = 1 + \max_{(u, v) \in E} \text{Layer}(v)$$
Relaksasi nilai lapisan konvergen dalam paling banyak $|V|$ iterasi pada DAG.

### 2.3 Perhitungan Jalur Kritis (Critical Longest Path)
Panjang ketergantungan maksimum dihitung menggunakan pemrograman dinamis di atas urutan topologis:
$$\text{dist}(u) = \max_{(p, u) \in E} (\text{dist}(p) + 1)$$
Jalur berjarak terpanjang adalah rantai ketergantungan modular terdalam yang memerlukan perhatian khusus saat rilis orkestrasi deploy.

---

## 3. Sertifikat Verifikasi Formal (`TopologicalCertificateVerifier`)
Memverifikasi:
1. Kesetaraan himpunan simpul: $|L| = |V|$ dan $L$ tidak memuat duplikasi.
2. Invarian urutan topologis: Untuk setiap busur terarah $(u, v) \in E$, indeks posisi $u$ dalam $L$ harus mendahului indeks $v$:
   $$\text{pos}(u) < \text{pos}(v)$$
Pelanggaran aturan ini membuktikan adanya siklus atau cacat algoritma pengurutan.

