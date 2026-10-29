@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Pendaftaran Pasien Rawat Jalan</h2>
            <p class="mt-1 text-sm text-gray-400">Pendaftaran pasien umum dan penjamin JKN/BPJS dengan antrian per poli.</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 p-4 bg-rose-900/50 border border-rose-500 rounded text-rose-200 text-sm space-y-1">
                    @foreach($errors->all() as $err)
                        <div>• {{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('hospital.outpatient.registration.store') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300">NIK Pasien (16 digit)</label>
                        <input type="text" name="nik" value="{{ old('nik') }}" required class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm" placeholder="Contoh: 3171012345670001">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300">Nama Lengkap Pasien</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm" placeholder="Nama sesuai KTP">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300">Tanggal Lahir</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300">Golongan Darah</label>
                        <select name="blood_type" class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm">
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="AB">AB</option>
                            <option value="O" selected>O</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300">Poliklinik Tujuan</label>
                        <select name="polyclinic_id" required class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm">
                            @foreach($polyclinics as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300">Dokter Penanggung Jawab</label>
                        <select name="doctor_id" required class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm">
                            @foreach($polyclinics as $p)
                                @foreach($p->doctors as $doc)
                                    <option value="{{ $doc->id }}">{{ $doc->user->name ?? $doc->doctor_code }} ({{ $p->name }})</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300">Metode Pembayaran</label>
                        <select name="payment_method" class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm">
                            <option value="CASH">Tunai / Mandiri (CASH)</option>
                            <option value="BPJS">Penjamin JKN (BPJS)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300">Nomor Kartu BPJS (Jika JKN)</label>
                        <input type="text" name="bpjs_card_number" value="{{ old('bpjs_card_number') }}" class="mt-1 block w-full rounded-md bg-gray-900 border-gray-700 text-white text-sm" placeholder="13 digit nomor kartu">
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md text-sm">
                        Daftarkan Pasien & Cetak Antrian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
