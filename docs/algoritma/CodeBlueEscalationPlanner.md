# Algoritma: CodeBlueEscalationPlanner (B5)

## 1. Intuisi & Latar Belakang
Code Blue menandakan situasi henti jantung (cardiac arrest) atau kegagalan pernapasan mendadak pada pasien rawat inap. Keterlambatan hitungan detik dapat berakibat fatal. Algoritma `CodeBlueEscalationPlanner` mengamati telemetri vital pasien secara kontinu dan deterministik, mendeteksi pelanggaran ganda dalam jendela waktu sempit, menaikkan alarm Code Blue tepat sekali per episode, serta merencanakan rantai eskalasi waktu nyata yang terikat audit kriptografis.

## 2. Aturan Deteksi & Eskalasi Bertingkat
1. **Kriteria Pelanggaran Vital (Vital Breach)**:
   - Saturasi Oksigen $\text{SpO2} < 85\%$, ATAU
   - Denyut Nadi $< 40$ bpm (bradikardia berat), ATAU
   - Denyut Nadi $> 150$ bpm (takikardia berat).
2. **Kondisi Aktivasi (Trigger Condition)**:
   - Alarm Code Blue dinaikkan **TEPAT SEKALI** per episode perawatan ketika terdeteksi **minimal 2 kali pelanggaran** dalam jendela waktu 3 menit.
   - Kejadian yang hanya memiliki 1 pelanggaran atau pelanggaran yang terpisah di luar jendela 3 menit tidak mengaktifkan Code Blue.
3. **Jadwal Eskalasi Waktu Nyata**:
   - Menit ke-0 (saat alarm naik): Notifikasi broadcast seketika ke `PERAWAT` jaga bangsal.
   - Menit ke-2 (+2 menit sejak alarm naik): Notifikasi eskalasi ke `DOKTER` jaga jika alarm belum di-acknowledge.
   - Menit ke-5 (+5 menit sejak alarm naik): Notifikasi darurat ke `TIM` resusitasi rumah sakit jika belum ada acknowledgement.
4. **Penghentian Eskalasi (Acknowledgement Guard)**:
   - Begitu staf klinis melakukan konfirmasi penerimaan (`acknowledge`), eskalasi tingkat berikutnya dibatalkan dan status berpindah ke `acknowledged`.

## 3. Vektor Uji Resmi
- **Rangkaian Telemetri (Menit, SpO2, Nadi)**:
  - $t_0$: (90, 80) -> Normal
  - $t_1$: (83, 100) -> Pelanggaran 1 (SpO2 83 < 85)
  - $t_2$: (82, 100) -> Pelanggaran 2 (SpO2 82 < 85) dalam jendela 3 menit!
  - $t_3$: (81, 100)
  - $t_4$: (80, 100)

- **Hasil Evaluasi**:
  1. Alarm Code Blue **NAIK PADA MENIT KE-2** ($t_2$).
  2. Skenario A (Acknowledgement pada Menit ke-3):
     - Tim yang menerima alert: `[PERAWAT@2]`. Karena di-ack pada menit 3, eskalasi menit 4 dan 7 dibatalkan.
  3. Skenario B (Tanpa Acknowledgement sama sekali):
     - Rencana eskalasi lengkap: `[PERAWAT@2, DOKTER@4, TIM@7]`.
  4. Skenario C (Acknowledgement pada Menit ke-5):
     - Tim yang menerima alert: `[PERAWAT@2, DOKTER@4]`. Eskalasi menit 7 dibatalkan.
  5. Skenario D (Pelanggaran terisolasi menit 0 dan menit 5 saja):
     - Menit 0 SpO2 83, Menit 1-4 normal, Menit 5 SpO2 83 -> Jarak waktu 5 menit (> 3 menit) -> **TIDAK NAIK**.

## 4. Sertifikat & Verifikasi
Hasil perencana menghasilkan `CodeBlueEscalationCertificate` yang mencatat hash rantai CanonicalHashChain dari riwayat telemetri, waktu deteksi, dan daftar notifikasi. Kelas `CodeBlueCertificateVerifier` memvalidasi keutuhan rantai dan kebenaran jendela waktu pelanggaran.

- Kompleksitas: $O(K)$ di mana $K$ adalah jumlah titik telemetri dalam riwayat (efektif $O(1)$ untuk sliding window 3 menit).
- Lokasi Kode: `Modules\Hospital\Domain\Algorithms\CodeBlueEscalationPlanner.php`

