@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1>Manajemen Merek Pangan & Program Gizi (Lini 21)</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Daftar Merek Pangan</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Merek</th>
                        <th>Deskripsi</th>
                        <th>Jumlah Produk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr>
                            <td>{{ $brand->code }}</td>
                            <td>{{ $brand->name }}</td>
                            <td>{{ $brand->description }}</td>
                            <td>{{ $brand->products->count() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada merek terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
