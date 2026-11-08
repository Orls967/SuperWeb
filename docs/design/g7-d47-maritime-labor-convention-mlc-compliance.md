# G7-D47: Maritime Labour Convention (MLC 2006) Compliance & Crew Welfare Standard

## 1. Konteks Regulasi Ketenagakerjaan Maritim Internasional

*Maritime Labour Convention, 2006* (MLC 2006) dari Organisasi Perburuhan Internasional (ILO) menetapkan hak-hak mendasar bagi seluruh pelaut (*Seafarers' Bill of Rights*). Regulasi ini mencakup kondisi kerja yang layak, jaminan upah tepat waktu, batas waktu kerja dan istirahat untuk mencegah kelelahan fatal (*fatigue*), akomodasi dan makanan sehat, serta jaminan perlindungan asuransi repatriasi.

Keputusan arsitektur **G7-D47** menetapkan standarisasi pencatatan jam kerja dan istirahat pelaut, validasi sertifikasi STCW, perhitungan gaji lintas mata uang (*multi-currency crew payroll*), dan penguncian izin layar kapal (*sailing clearance gate*) bila standar awak kapal aman (*Minimum Safe Manning*) tidak terpenuhi.

---

## 2. Batasan Jam Istirahat Regulasi Standar A2.3 MLC 2006

Untuk mencegah kecelakaan akibat kelalaian navigasi karena kelelahan kru:

| Batasan Regulasi | Durasi Waktu Wajib | Pengecualian Darurat |
|---|---|---|
| **Istirahat Harian Minimum** | Minimal **10 jam** dalam setiap periode 24 jam. | Dapat dibagi menjadi maksimal 2 periode, salah satunya minimal berdurasi 6 jam berturut-turut. Jeda antara periode istirahat tidak boleh melebihi 14 jam. |
| **Istirahat Mingguan Minimum** | Minimal **77 jam** dalam setiap periode 7 hari kalender. | Tidak boleh ada kompensasi lembur yang mengurangi batas istirahat mingguan ini. |
| **Batas Kerja Maksimum** | Maksimal **14 jam** dalam setiap periode 24 jam, atau maksimal **72 jam** dalam setiap periode 7 hari. | Diperkenankan hanya dalam kondisi darurat keselamatan kapal, penyelamatan jiwa di laut (*search and rescue*), atau latihan sekoci wajib (*muster drill*). |

---

## 3. Matriks Minimum Awak Kapal Aman (Minimum Safe Manning Document - MSMD)

Setiap kapal ocean-going wajib diawaki oleh personil bersertifikat kompetensi (CoC) dan kemahiran (CoP) sesuai standar STCW 1978 amandemen 2010:

```
+---------------------------------------------------------------+
|                       KOMPOSISI AWAK MINIMUM                  |
+-------------------------------+-------------------------------+
| Departemen Dek (Deck)         | Departemen Mesin (Engine)     |
| - 1x Nahkoda (Master Class I) | - 1x KKM / Chief Eng (Class I)|
| - 1x Mualim I (Chief Officer) | - 1x Masinis II (2nd Engineer)|
| - 1x Mualim II (2nd Officer)  | - 1x Masinis III (3rd Engineer|
| - 1x Mualim III (3rd Officer) | - 1x Perwira Listrik (ETO)    |
| - 1x Bosun (Kepala Kelasi)    | - 1x Mandor Mesin (Fitter)    |
| - 3x Juru Mudi (Able Seaman)  | - 2x Juru Minyak (Oiler)      |
| - 2x Kelasi Biasa (OS)        |                               |
+-------------------------------+-------------------------------+
| Katering: 1x Koki Kapal (Chief Cook) Bersertifikat MLC Food   |
+---------------------------------------------------------------+
```

---

## 4. Audit Digital Kepatuhan Jam Istirahat (CrewRotationAndPayrollService)

Layanan `CrewRotationAndPayrollService` menjalankan validasi otomatis:
1. Setiap entri jam istirahat harian dianalisis secara algoritmis terhadap jendela geser 24 jam dan 7 hari.
2. Jika terdeteksi pelanggaran (*rest hour deficiency*), sistem menerbitkan peringatan merah dan mencatat insiden ketidaksesuaian (*Non-Conformity Notice*) pada audit ISM Code.
3. Rekapitulasi lembur dan tunjangan sandar pelabuhan dihitung transparan dengan slip gaji digital (*digital payslip*) berstandar MLC Regulation 2.2.

