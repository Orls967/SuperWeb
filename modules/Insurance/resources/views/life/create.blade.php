@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="{{ route('insurance.life.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">&larr; Kembali ke Daftar Polis Jiwa</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Penerbitan Polis Asuransi Jiwa Baru</h1>
        <p class="mt-1 text-sm text-gray-600">Simulasi perhitungan aktuaria komutasi A7 dan registrasi penerima manfaat.</p>
    </div>

    <div class="bg-white shadow sm:rounded-lg p-6">
        <form method="POST" action="{{ route('insurance.life.store') }}" class="space-y-6">
            @csrf
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nomor Polis</label>
                    <input type="text" name="policy_number" value="POL-LIFE-{{ date('Ymd') }}-{{ rand(100,999) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Jenis Rencana (Plan)</label>
                    <select name="plan_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                        <option value="TERM_LIFE">Term Life (Berjangka Murni)</option>
                        <option value="ENDOWMENT_SAVING">Endowment Saving (Dwiguna Tabungan)</option>
                        <option value="WHOLE_LIFE">Whole Life (Seumur Hidup)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Usia Tertanggung (Tahun)</label>
                    <input type="number" name="entry_age" value="30" min="18" max="75" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Masa Pertanggungan (Tahun)</label>
                    <input type="number" name="term_years" value="20" min="1" max="40" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Uang Pertanggungan (IDR Minor Unit)</label>
                    <input type="number" name="sum_insured_idr" value="500000000" min="10000000" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm" required>
                </div>
            </div>

            <div class="pt-5 border-t border-gray-200 flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Hitung Aktuaria & Terbitkan</button>
            </div>
        </form>
    </div>
</div>
@endsection

