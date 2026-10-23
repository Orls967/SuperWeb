@extends('layouts.app')

@section('title', 'Biometric Shift Attendance')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Biometric Shift Attendance & Overtime Processing</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Pencatatan presensi biometrik karyawan terintegrasi dengan jadwal shift kerja pabrik, kalkulasi otomatis keterlambatan dan jam lembur.
            </div>
            <form action="{{ route('hcm.operations.attendance.record') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee ID (UUID)</label>
                        <input type="text" name="employee_id" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Presensi</label>
                        <input type="date" name="log_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Clock In</label>
                        <input type="time" name="clock_in" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Clock Out</label>
                        <input type="time" name="clock_out" class="form-control">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Catat Presensi Biometrik</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
