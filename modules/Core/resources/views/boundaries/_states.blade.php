{{-- Shared 5 UI States (T-6b): kosong, memuat, galat, tanpa akses, sukses (toast aria-live) --}}
@if (session('success') || session('status'))
    <x-ds.toast type="success" title="Berhasil" :message="session('success') ?? session('status')" />
@endif

@if (session('error') || ($errors->any() ?? false))
    <x-ds.error
        title="Validasi / Operasi Gagal"
        :message="session('error') ?? $errors->first()"
        code="422"
    />
@endif

@if (request()->boolean('loading'))
    <x-ds.skeleton type="table" :rows="4" :columns="4" />
@endif

@if (request()->boolean('denied'))
    <x-ds.error
        title="Tanpa Akses"
        message="Peran Anda tidak memiliki izin untuk melakukan aksi arsitektur pada halaman ini."
        code="403"
    />
@endif

