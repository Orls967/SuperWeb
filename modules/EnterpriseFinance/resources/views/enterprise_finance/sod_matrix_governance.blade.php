@extends('layouts.app')

@section('title', 'Matriks Pemisahan Tugas (SoD) & Tata Kelola Korporat')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Matriks Segregation of Duties (SoD) & Pencegahan Fraud Korporat (54.4)</h5>
            <span class="badge bg-warning text-dark">ISO 27001 / COSO</span>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Matriks kombinasi peran terlarang untuk memitigasi risiko kecurangan dan penyalahgunaan wewenang. Kombinasi peran bertingkat CRITICAL memberlakukan blokade mutlak (Hard-Stop) pada tingkat sistem.
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Kode Aturan</th>
                            <th scope="col">Peran Terlarang A</th>
                            <th scope="col">Peran Terlarang B</th>
                            <th scope="col">Tingkat Risiko</th>
                            <th scope="col">Deskripsi Pengendalian</th>
                            <th scope="col">Kebijakan Sistem</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sodRules ?? [] as $rule)
                            <tr>
                                <td class="font-monospace fw-bold">{{ $rule->rule_code }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $rule->role_a }}</span></td>
                                <td><span class="badge bg-light text-dark border">{{ $rule->role_b }}</span></td>
                                <td>
                                    <span class="badge {{ $rule->risk_level === 'CRITICAL' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                        {{ $rule->risk_level }}
                                    </span>
                                </td>
                                <td>{{ $rule->description }}</td>
                                <td>
                                    <span class="text-xs fw-semibold {{ $rule->risk_level === 'CRITICAL' ? 'text-danger' : 'text-secondary' }}">
                                        {{ $rule->risk_level === 'CRITICAL' ? 'HARD_STOP_REJECT' : 'WARN_AUDIT_LOG' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $rule->is_active ? 'AKTIF' : 'NON-AKTIF' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada aturan SoD yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
