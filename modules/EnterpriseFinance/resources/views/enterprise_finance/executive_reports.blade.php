@extends('layouts.app')

@section('title', 'Pusat Ekspor Laporan Keuangan Grup Bersertifikat')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Pusat Ekspor Laporan Keuangan Korporat Bersertifikat SHA-256</h5>
            <span class="badge bg-primary">Digital Audit Seal</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pengunduhan 8 paket laporan keuangan korporat terstandardisasi dalam format CSV dengan verifikasi integritas cryptographic hash SHA-256 untuk keperluan audit eksternal, dewan komisaris, dan kreditur sindikasi.
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="border rounded p-4 h-100 bg-light">
                        <h6 class="fw-bold mb-3">Parameter Filter Periode Laporan</h6>
                        <form method="GET" action="#">
                            <div class="mb-3">
                                <label for="report_period" class="form-label">Periode Bulan Laporan (YYYY-MM)</label>
                                <input type="text" id="report_period" name="report_period" class="form-control font-monospace" placeholder="2026-10" aria-label="Periode Bulan Laporan">
                            </div>
                            <div class="mb-3">
                                <label for="fiscal_year" class="form-label">Tahun Fiskal (YYYY)</label>
                                <input type="text" id="fiscal_year" name="fiscal_year" class="form-control font-monospace" placeholder="2026" aria-label="Tahun Fiskal">
                            </div>
                            <button type="button" class="btn btn-outline-secondary" aria-label="Terapkan Filter Laporan">Terapkan Filter</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-4 h-100 bg-light">
                        <h6 class="fw-bold mb-3">Informasi Sertifikasi Hash Digital</h6>
                        <p class="text-sm text-muted">Setiap berkas CSV yang diekspor dilengkapi dengan segel tanda tangan SHA-256 yang ditautkan pada blok rantai audit perbankan. Segala perubahan manual pada baris atau angka laporan akan membatalkan validitas segel digital tersebut.</p>
                        <div class="p-3 bg-white border rounded">
                            <span class="text-xs text-muted d-block">Status Verifikator Kriptografi:</span>
                            <span class="badge bg-success">SHA-256 CRYPTO VERIFIED</span>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="fw-bold mb-3">Katalog 8 Laporan Keuangan Grup</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Nama Dokumen Laporan</th>
                            <th scope="col">Regulasi & Standar</th>
                            <th scope="col">Cakupan Data</th>
                            <th scope="col">Tindakan Unduh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td class="fw-bold">Consolidated Trial Balance Report</td>
                            <td>PSAK 65 / IFRS 10</td>
                            <td>Neraca saldo agregat holding & anak entitas</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh Consolidated Trial Balance">Unduh CSV</button></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td class="fw-bold">Department Budget Variance Report</td>
                            <td>COSO Internal Control</td>
                            <td>Varians pagu alokasi, encumbrance & belanja riil</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh Budget Variance">Unduh CSV</button></td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td class="fw-bold">Syndicated Loan Covenant Compliance</td>
                            <td>LMA Syndication Standard</td>
                            <td>Evaluasi rasio DSCR, DER, ISCR, Current Ratio</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh Covenant Compliance">Unduh CSV</button></td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td class="fw-bold">13-Week Cash Liquidity Forecast</td>
                            <td>Corporate Treasury Policy</td>
                            <td>Proyeksi arus kas likuid mingguan grup 13 horizon</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh Cash Forecast">Unduh CSV</button></td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td class="fw-bold">Working Capital Optimization (CCC)</td>
                            <td>Operational Excellence</td>
                            <td>Metrik DSO, DIO, DPO & konversi kas operasional</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh Working Capital">Unduh CSV</button></td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td class="fw-bold">Segregation of Duties (SoD) Matrix</td>
                            <td>ISO 27001 / SOX 404</td>
                            <td>Matriks pencegahan konflik wewenang & otorisasi</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh SoD Matrix">Unduh CSV</button></td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td class="fw-bold">Regulatory Compliance Calendar</td>
                            <td>OJK, DJP, BEI, Kemendag</td>
                            <td>Agenda tenggat waktu kepatuhan & eskalasi SLA</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh Compliance Calendar">Unduh CSV</button></td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td class="fw-bold">Executive Consolidated Financial Summary</td>
                            <td>Executive Committee Board</td>
                            <td>17-lines segment reporting & ringkasan EBITDA</td>
                            <td><button type="button" class="btn btn-sm btn-outline-primary" aria-label="Unduh Executive Summary">Unduh CSV</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
