# Spesifikasi Algoritma — Radius Ledakan Ketergantungan Modular (Cross-Module Blast Radius)

## 1. Konteks & Latar Belakang
Dalam arsitektur modular berskala besar dengan lebih dari 65 modul bisnis dan kernel di SuperWeb, perubahan pada satu modul penyedia (*provider*) berpotensi menimbulkan dampak riak (*ripple effect*) terhadap modul-modul konsumen (*consumers*), baik secara langsung maupun transitif. Tanpa pengukuran dampak yang presisi, refaktorisasi atau perubahan skema dapat memicu regresi tersembunyi pada modul hilir.

Algoritma `CrossModuleBlastRadiusAnalyzer` mengkuantifikasi jangkauan perubahan, mengidentifikasi seluruh konsumen hilir, kedalaman rambatan maksimum, serta menghitung skor risiko dampak terstandardisasi.

---

## 2. Definisi Graf & Formulasi Matematika

### 2.1 Representasi Graf Ketergantungan
Diberikan graf ketergantungan terarah $G = (V, E)$, di mana:
- $V = \{m_1, m_2, \dots, m_n\}$ adalah himpunan modul sistem ($|V| \approx 65$).
- $E \subseteq V \times V$ adalah himpunan busur terarah ketergantungan, di mana busur $(u, v) \in E$ menyatakan modul konsumen $u$ bergantung pada modul penyedia $v$.

### 2.2 Graf Konsumen Terbalik (Inverted Consumer Graph)
Untuk menganalisis dampak dari modul target $t \in V$ ke arah hilir, dibentuk graf terbalik $G^R = (V, E^R)$:
$$E^R = \{(v, u) \mid (u, v) \in E\}$$
Pada graf $G^R$, busur terarah $(t, u)$ menyatakan bahwa modul $u$ mengonsumsi modul $t$.

### 2.3 Penutupan Transitif & Kedalaman Riak (Transitive Closure & Ripple Depth)
- **Konsumen Langsung** ($C_{\text{dir}}(t)$):
  $$C_{\text{dir}}(t) = \{u \in V \mid (t, u) \in E^R\}$$
- **Konsumen Transitif** ($C_{\text{trans}}(t)$):
  Himpunan semua modul yang dapat dicapai dari $t$ pada graf $G^R$ melalui penelusuran Breadth-First Search (BFS):
  $$C_{\text{trans}}(t) = \{u \in V \mid \text{terdapat jalur berarah } t \leadsto u \text{ di } G^R\}$$
- **Kedalaman Riak Maksimum** ($\delta_{\text{max}}(t)$):
  $$\delta_{\text{max}}(t) = \max_{u \in C_{\text{trans}}(t)} \text{dist}_{G^R}(t, u)$$
  di mana $\text{dist}_{G^R}(t, u)$ adalah panjang jalur terpendek dari $t$ ke $u$ pada $G^R$.

### 2.4 Jalur Dampak Kritis (Critical Impact Paths)
Jalur terpanjang tanpa siklus dari $t$ ke modul konsumen terminal (konsumen yang tidak dikonsumsi lagi oleh modul lain) didefinisikan sebagai jalur kritis. Jalur ini merepresentasikan rantai kegagalan terpanjang jika kontrak modul $t$ berubah.

---

## 3. Formulasi Skor Dampak Integer Permil (0..1000)

Skor dampak dihitung murni menggunakan aritmetika bilangan bulat permil guna mencegah divergensi *floating-point*:

$$\text{ReachRatio} = \left\lfloor \frac{|C_{\text{trans}}(t)| \times 1.000}{|V|} \right\rfloor$$

$$\text{DepthFactor} = \min\left(1.000, \, \delta_{\text{max}}(t) \times 200\right)$$

$$\text{RawRisk} = \left\lfloor \left(\text{ReachRatio} \times \frac{600}{1.000}\right) + \left(\text{DepthFactor} \times \frac{400}{1.000}\right) \right\rfloor$$

Faktor cakupan kontrak modul target $S_{\text{cov}}(t) \in [0, 1.000]$ (makin tinggi cakupan kontrak formal, makin rendah risiko perubahan tidak terduga):

$$\text{ImpactScore}(t) = \left\lfloor \frac{\text{RawRisk} \times \left(1.000 - \left\lfloor \frac{S_{\text{cov}}(t)}{2} \right\rfloor\right)}{1.000} \right\rfloor$$

---

## 4. Kompleksitas Komputasi
- **Waktu**: $\mathcal{O}(|V| + |E|)$ untuk pembentukan graf terbalik dan penelusuran BFS tingkat kedalaman.
- **Ruang**: $\mathcal{O}(|V| + |E|)$ untuk struktur data antrean dan pemetaan jarak.

---

## 5. Sertifikat Verifikasi Formal (`BlastRadiusCertificateVerifier`)
Verifier memvalidasi invarian matematis keluaran:
1. $C_{\text{dir}}(t) \subseteq C_{\text{trans}}(t)$.
2. Untuk setiap $u \in C_{\text{dir}}(t)$, busur $(u, t) \in E$ terbukti ada pada graf asli.
3. $\text{ImpactScore}(t) \in [0, 1.000]$ permil.
4. Setiap jalur kritis berarah valid dan bebas siklus.

