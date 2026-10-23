@extends('layouts.app')

@section('title', 'Enterprise Payroll, PPh 21 TER & BPJS Calculation')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Payroll Engine: PPh 21 TER & BPJS TK/Kes Compliance</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Kalkulasi penggajian korporat: gaji pokok, tunjangan, lembur, potongan PPh 21 TER, iuran BPJS Kesehatan (4%+1%) & BPJS Ketenagakerjaan (JKK, JKM, JHT 3.7%+2%, JP 2%+1%) dengan pembukuan otomatis ke LedgerService.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Komponen Gaji</th>
                        <th>Kategori Beban</th>
                        <th>Formula / Tarif</th>
                        <th>Dasar Upah Maksimal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>BPJS Kesehatan (Pemberi Kerja / Pekerja)</td>
                        <td>Beban Perusahaan & Potongan Gaji</td>
                        <td>4% Perusahaan + 1% Karyawan</td>
                        <td>Maksimal Rp 12.000.000</td>
                    </tr>
                    <tr>
                        <td>BPJS TK - JHT (Jaminan Hari Tua)</td>
                        <td>Tabungan Hari Tua</td>
                        <td>3.7% Perusahaan + 2.0% Karyawan</td>
                        <td>Tanpa Batas Atas Upah</td>
                    </tr>
                    <tr>
                        <td>BPJS TK - JP (Jaminan Pensiun)</td>
                        <td>Pensiun Berkala</td>
                        <td>2.0% Perusahaan + 1.0% Karyawan</td>
                        <td>Maksimal Rp 10.042.300</td>
                    </tr>
                    <tr>
                        <td>PPh 21 TER (Tarif Efektif Rata-Rata)</td>
                        <td>Pajak Penghasilan Karyawan</td>
                        <td>Kategori A/B/C PP 58/2023</td>
                        <td>Progressif Berdasarkan Penghasilan Bruto</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
