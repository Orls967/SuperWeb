# G7-D53: Vessel Hull Geometry & Intact Stability Standard (IMO 2008 IS Code)

## 1. Konteks Keteknikan Maritim & Teori Bangunan Kapal

Stabilitas kapal (*Ship Stability*) merupakan faktor primer yang menentukan keselamatan sebuah kapal dari resiko terbalik (*capsizing*) saat berhadapan dengan gelombang samudera dan momen kemiringan akibat hembusan angin badai. Organisasi Maritim Internasional (IMO) menetapkan standar kepatuhan minimum melalui *International Code on Intact Stability, 2008* (2008 IS Code).

Keputusan arsitektur **G7-D53** menetapkan integrasi kalkulasi hidrodinamika bentuk lambung kapal (*hull form coefficients*) dan verifikasi tinggi metasentris (*metacentric height - GM*) sebelum setiap keberangkatan kapal dari pelabuhan.

---

## 2. Koefisien Bentuk Lambung Kapal (Hull Form Coefficients)

Karakteristik hidrodinamika lambung dihitung oleh `MaritimeVesselGeometryCalculator`:

| Koefisien Lambung | Simbol | Formula Hidrodinamika | Makna Fisik & Nilai Tipikal |
|---|---|---|---|
| **Block Coefficient** | $C_b$ | $$C_b = \frac{\nabla}{L_{\text{pp}} \times B \times T}$$ | Mengukur kepenuhan badan kapal di bawah garis air dibanding balok prisma. Bulk carrier: $0.80 - 0.85$, Container: $0.60 - 0.70$. |
| **Midship Section Coefficient** | $C_m$ | $$C_m = \frac{A_m}{B \times T}$$ | Rasio luas penampang tengah kapal terhadap persegi panjang $B \times T$. Kapal niaga umumnya berkisar antara $0.95 - 0.98$. |
| **Prismatic Coefficient** | $C_p$ | $$C_p = \frac{\nabla}{A_m \times L_{\text{pp}}} = \frac{C_b}{C_m}$$ | Mengukur distribusi volume lambung di sepanjang haluan dan buritan kapal. Mempengaruhi resistensi gelombang propulsi. |
| **Waterplane Area Coefficient** | $C_{\text{wp}}$ | $$C_{\text{wp}} = \frac{A_w}{L_{\text{pp}} \times B}$$ | Rasio luas bidang air pada sarat operasi terhadap persegi panjang garis air. Menentukan momen inersia bidang air dan titik metasenter. |

---

## 3. Evaluasi Stabilitas Utuh (Intact Stability & GM Calculation)

### 3.1 Penentuan Titik Kritis Kapal
1. **Titik Berat Kapal (Center of Gravity - G)**: Tinggi titik berat di atas lunas ($KG$) dipengaruhi oleh distribusi berat kapal kosong (*lightship*), kargo di palka, bahan bakar di tangki dasar ganda, dan air balas.
2. **Titik Apung Kapal (Center of Buoyancy - B)**: Titik tangkap gaya apung ke atas air laut ($KB$), secara empiris didekati dengan formula Morrish:
$$KB \approx T \times \left( \frac{1}{3} + \frac{5 \nabla}{6 A_w T} \right) \approx T \times \left( \frac{5}{6} - \frac{C_b}{6 C_{\text{wp}}} \right)$$
3. **Radius Metasentris (Transverse Metacentric Radius - BM)**: Jarak antara titik apung ke metasenter melintang ($M$):
$$BM = \frac{I_T}{\nabla} \approx \frac{C_{\text{wp}}^2 \times B^2}{12 \times C_b \times T}$$
4. **Tinggi Metasentris Awal (Initial Metacentric Height - GM)**:
$$KM = KB + BM$$
$$GM = KM - KG$$

### 3.2 Standar Kriteria IMO 2008 IS Code
Sesuai Bagian A Kriteria 2.2 Kode IS 2008:
- **Tinggi Metasentris Minimum**: Nilai $GM$ awal setelah koreksi permukaan bebas cairan tangki (*Free Surface Effect - FSE*) **tidak boleh kurang dari 0.15 meter** ($GM \ge 0.15\,\text{m}$) untuk seluruh kondisi muat pelayaran.
- **Lengan Penegak Maksimum**: Nilai lengan penegak ($GZ$) maksimum harus terjadi pada sudut kemiringan $\theta \ge 30^\circ$ dan bernilai minimal $0.20\,\text{m}$.
- **Luas Kurva Stabilitas Statis**: Luas di bawah kurva $GZ$ hingga sudut kemiringan $30^\circ$ tidak boleh kurang dari $0.055\,\text{meter-radian}$, dan hingga $40^\circ$ tidak boleh kurang dari $0.090\,\text{meter-radian}$.

---

## 4. Gerbang Keselamatan Keberangkatan Kapal (Pre-Departure Stability Gate)

Setiap rencana muatan kapal dievaluasi oleh sistem sebelum persetujuan keberangkatan (*port clearance approval*):
1. Jika nilai kalkulasi $GM < 0.15\,\text{m}$, kapal berada dalam kondisi stabilitas kritis (*tender ship*) dengan resiko terbalik tinggi.
2. Sistem secara otomatis menolak penerbitan izin layar dan menginstruksikan pengisian air balas pada tangki dasar ganda (*double bottom ballast tanks*) untuk menurunkan titik berat $KG$ hingga batas aman tercapai.

