# Spesifikasi Algoritma — Metrik Kopling Paket & Matriks Ketergantungan Modular

## 1. Konteks Teoretis
Metrik arsitektur modul diturunkan dari prinsip desain modular Robert C. Martin (*Principles of Package Architecture*, 1994, 2002):
1. **Stable Dependencies Principle (SDP)**: Ketergantungan antar paket/modul harus mengarah ke paket yang lebih stabil.
2. **Stable Abstractions Principle (SAP)**: Kestabilan suatu paket/modul berbanding lurus dengan derajat keabstrakannya.

Algoritma `ModuleCouplingMatrixScorer` memodelkan dan mengevaluasi keseimbangan stabilitas dan keabstrakan seluruh 65 modul pada platform SuperWeb.

---

## 2. Definisi Parameter & Formulasi Matematika

### 2.1 Kopling Aferen & Eferen
Diberikan graf modul $G = (V, E)$:
- **Afferent Coupling ($C_a$)**: Jumlah modul eksternal yang bergantung pada kelas/komponen di dalam modul ini (indikator tanggung jawab dan stabilitas).
  $$C_a(m) = |\{u \in V \setminus \{m\} \mid (u, m) \in E\}|$$
- **Efferent Coupling ($C_e$)**: Jumlah modul eksternal yang diakses oleh kelas di dalam modul ini (indikator ketergantungan).
  $$C_e(m) = |\{v \in V \setminus \{m\} \mid (m, v) \in E\}|$$

### 2.2 Hukum Kekekalan Busur Graf Tertutup
Dalam sistem graf terarah tertutup tanpa ketergantungan eksternal pihak ketiga, total kopling aferen sama persis dengan total kopling eferen:
$$\sum_{m \in V} C_a(m) = \sum_{m \in V} C_e(m) = |E|$$
Invarian ini menjadi dasar verifikasi formal pada `CouplingMatrixCertificateVerifier`.

### 2.3 Ketidakstabilan Paket (Instability $I$)
Rasio ketergantungan keluar terhadap total interaksi modul:
$$I(m) = \begin{cases}
\left\lfloor \frac{C_e(m) \times 1.000}{C_a(m) + C_e(m)} \right\rfloor & \text{jika } C_a(m) + C_e(m) > 0 \\
500 & \text{jika } C_a(m) + C_e(m) = 0 \text{ (terisolasi)}
\end{cases}$$
- $I = 0$: Modul sangat stabil (banyak modul lain bergantung padanya, ia tidak bergantung pada modul luar).
- $I = 1.000$: Modul sangat labil (tidak ada yang bergantung padanya, ia bergantung pada banyak modul luar).

### 2.4 Keabstrakan Paket (Abstractness $A$)
Rasio antarmuka (*interfaces/contracts*) terhadap total kelas/komponen dalam modul:
$$A(m) = \left\lfloor \frac{N_{\text{contracts}}(m) \times 1.000}{N_{\text{total\_classes}}(m)} \right\rfloor$$
- $A = 0$: Modul konkret murni (seluruh berkas berupa kelas implementasi).
- $A = 1.000$: Modul abstrak murni (seluruh berkas berupa interface/kontrak abstrak).

### 2.5 Jarak dari Garis Utama (Distance from Main Sequence $D$)
Garis utama (*Main Sequence*) didefinisikan oleh persamaan ideal keseimbangan:
$$A + I = 1.000$$
Deviasi modul dari garis keseimbangan diukur dengan jarak ortogonal ternormalisasi:
$$D(m) = \min(1.000, \, |A(m) + I(m) - 1.000|)$$

---

## 3. Klasifikasi Zona Arsitektur

Modul diklasifikasikan ke dalam 3 kategori struktural:
1. **Zone of Pain** ($A < 300$, $I < 300$, $C_a \ge 3$):
   Modul sangat konkret namun sangat stabil karena banyak dikonsumsi oleh modul lain. Kondisi ini membuat modul kaku dan sulit diubah tanpa risiko merusak banyak modul konsumen.
2. **Zone of Uselessness** ($A > 700$, $I > 700$, $C_a = 0$):
   Modul sangat abstrak namun tidak ada modul lain yang mengonsumsinya. Merupakan abstraksi tanpa guna (*over-engineering*).
3. **Balanced Main Sequence**:
   Modul berada pada zona keseimbangan arsitektur yang ideal di mana derajat stabilitas sebanding dengan tingkat keabstrakan.

---

## 4. Sertifikat Verifikasi Formal (`CouplingMatrixCertificateVerifier`)
Memastikan:
1. Validasi kekekalan busur: $\sum C_a = \sum C_e$.
2. Batas integer permil: $I, A, D \in [0, 1.000]$.
3. Konsistensi nilai deviasi $D = |A + I - 1.000|$.

