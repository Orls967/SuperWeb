@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2>Tagihan Terminal (Terminal Billing)</h2>
    <div class="billing-table my-4">
        <table class="table">
            <thead>
                <tr>
                    <th>No Tagihan</th>
                    <th>Vessel Call</th>
                    <th>Line Party</th>
                    <th>THC</th>
                    <th>Dockage</th>
                    <th>Storage</th>
                    <th>D&D</th>
                    <th>Total IDR</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bills as $bill)
                    <tr>
                        <td>{{ $bill->bill_number }}</td>
                        <td>{{ $bill->vesselCall?->vessel_name ?? '-' }}</td>
                        <td>Party #{{ $bill->line_party_id }}</td>
                        <td>{{ number_format($bill->thc_amount_idr) }}</td>
                        <td>{{ number_format($bill->dockage_amount_idr) }}</td>
                        <td>{{ number_format($bill->storage_amount_idr) }}</td>
                        <td>{{ number_format($bill->dnd_amount_idr) }}</td>
                        <td><strong>{{ number_format($bill->amount_idr) }}</strong></td>
                        <td>{{ $bill->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">Belum ada tagihan terminal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $bills->links() }}
    </div>
</div>
@endsection
