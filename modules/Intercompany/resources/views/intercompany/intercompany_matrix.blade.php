@extends('layouts.app')

@section('title', 'Intercompany Consolidation Matrix')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Group Intercompany & Consolidation Matrix</h3>
            <p class="text-muted mb-0">Bilateral Transactions, Arm’s Length Transfer Pricing, and Consolidation Eliminations</p>
        </div>
        <div>
            <a href="{{ route('intercompany.consolidation.index') }}" class="btn btn-primary">Refresh Matrix</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">Recent Mirrored Transactions</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Penjual -> Pembeli</th>
                                    <th>Nominal (IDR)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions ?? [] as $tx)
                                    <tr>
                                        <td>{{ $tx->transaction_code }}</td>
                                        <td>{{ $tx->selling_entity }} &rarr; {{ $tx->buying_entity }}</td>
                                        <td>Rp {{ number_format($tx->amount_idr, 0, ',', '.') }}</td>
                                        <td><span class="badge bg-success">{{ $tx->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No intercompany transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Posted Elimination Entries</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Periode</th>
                                    <th>Tipe</th>
                                    <th>Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($eliminations ?? [] as $elim)
                                    <tr>
                                        <td>{{ $elim->elimination_code }}</td>
                                        <td>{{ $elim->period }}</td>
                                        <td>{{ $elim->elimination_type }}</td>
                                        <td>Rp {{ number_format($elim->amount_idr, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No elimination entries posted.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
