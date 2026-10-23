@extends('layouts.app')

@section('title', 'Organizational Structure & Job Grades')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Enterprise Organizational Units & Position Bandwidth</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Manajemen struktur hierarki divisi, departemen, dan grading kompensasi karyawan sesuai standar tata kelola ketenagakerjaan.
            </div>
            <form action="{{ route('hcm.operations.department.create') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Kode Departemen</label>
                        <input type="text" name="code" class="form-control" placeholder="DEP-ENG-01" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Departemen</label>
                        <input type="text" name="name" class="form-control" placeholder="Plant Engineering & Maintenance" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Simpan Unit Organisasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
