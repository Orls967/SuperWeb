@extends('layouts.app')

@section('title', 'PLM Stage-Gate Governance (K-30 Guarded)')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Product Lifecycle Management: Sequential Stage-Gate Guard</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <strong>Aturan Ketat K-30:</strong> Proyek R&D dilarang melompati tahap Stage-Gate (Ideation &rarr; Scoping &rarr; Business Case &rarr; Development &rarr; Testing &rarr; Commercial Launch) tanpa verifikasi deliverables dan persetujuan komite.
            </div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Tahap (Stage)</th>
                        <th>Syarat Kelayakan (Deliverables)</th>
                        <th>Status Gate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1. Ideation</td>
                        <td>Konsep Produk & Problem-Solution Fit</td>
                        <td><span class="badge bg-success">Passed</span></td>
                    </tr>
                    <tr>
                        <td>2. Scoping</td>
                        <td>Alokasi Anggaran R&D Awal</td>
                        <td><span class="badge bg-success">Passed</span></td>
                    </tr>
                    <tr>
                        <td>3. Business Case</td>
                        <td>Proyeksi ROI & Analisis Kelayakan Bisnis</td>
                        <td><span class="badge bg-primary">Active</span></td>
                    </tr>
                    <tr>
                        <td>4. Development</td>
                        <td>Definisi Engineering BOM (EBOM) v1.0</td>
                        <td><span class="badge bg-secondary">Locked</span></td>
                    </tr>
                    <tr>
                        <td>5. Testing</td>
                        <td>Uji Stabilitas Laboratorium & Sensory Score</td>
                        <td><span class="badge bg-secondary">Locked</span></td>
                    </tr>
                    <tr>
                        <td>6. Commercial Launch</td>
                        <td>Konversi ke MBOM Manufaktur & Otorisasi Rilis Komersial</td>
                        <td><span class="badge bg-secondary">Locked</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
