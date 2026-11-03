@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1>Pabrik Pengolahan Komoditas Pertanian (Agri-Processing Lini 21)</h1>

    <div class="card mb-4">
        <div class="card-header">Batch Pengolahan & Neraca Massa</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Batch</th>
                        <th>Target SKU</th>
                        <th>Input (g)</th>
                        <th>Output (g)</th>
                        <th>Susut (g)</th>
                        <th>Status</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $b)
                        <tr>
                            <td>{{ $b->batch_code }}</td>
                            <td>{{ $b->target_product_sku }}</td>
                            <td>{{ number_format($b->planned_input_grams) }}</td>
                            <td>{{ number_format($b->actual_output_grams) }}</td>
                            <td>{{ number_format($b->waste_grams) }}</td>
                            <td><span class="badge bg-secondary">{{ $b->status }}</span></td>
                            <td>{{ $b->export_grade ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Belum ada batch pengolahan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
