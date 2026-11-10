# Glosarium & Terminologi Resmi Kernel Simulasi (Fase 67 & R8)

Dokumen ini mendefinisikan terminologi baku, akronim, dan singkatan yang digunakan dalam sistem Kernel Simulasi Waktu Virtual dan Event Spine platform Superweb.

---

## Daftar Istilah Baku

- **Clock Virtual:** Sumber waktu buatan terkompresi yang diatur oleh `SimClockInterface`, beroperasi secara diskret dalam satuan hari dan detik virtual.
- **Discrete Event:** Peristiwa bisnis atomik yang dijadwalkan pada detik virtual tertentu dengan prioritas dan nomor urut deterministik.
- **Digital Twin:** Bayangan digital persisten dari entitas fisik atau konseptual yang mencatat seluruh mutasi keadaan dalam bentuk rantai hash kriptografis.
- **Fingerprint Hash Chain:** Rantai kriptografis harian $h_i = \text{SHA256}(h_{i-1} \cdot \text{summary}_i)$ yang mengunci integritas eksekusi simulasi.
- **Kahn Algorithm:** Algoritma pemesanan topologis graf asiklik berarah (DAG) yang digunakan untuk menentukan urutan eksekusi `SimTickHandler`.
- **MINSTD:** Generator bilangan acak semu linear congruential baku Park-Miller ($a=48271, m=2^{31}-1$).
- **Sandbox Mode:** Mode eksekusi simulasi dalam savepoint/transaksi basis data yang selalu di-rollback di akhir sesi untuk mencegah kebocoran data tiruan.
- **Tick Handler:** Unit logika modul domain yang dieksekusi sekali sehari pada waktu virtual untuk memperbarui kondisi bisnis harian (misal night audit, penalti demurrage).
- **Time Compression:** Kemampuan sistem mengeksekusi siklus bisnis jangka panjang (misal 1 tahun) dalam hitungan detik komputasi riil.
- **Topological Order:** Urutan linier simpul graf di mana setiap prasyarat dijamin dieksekusi sebelum simpul yang bergantung padanya.

