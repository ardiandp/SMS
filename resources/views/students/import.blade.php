@extends('layouts.app')

@section('title', 'Impor Siswa')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Impor Siswa (CSV/XLSX)</h1>

    <div class="bg-white rounded shadow p-6 max-w-3xl mb-6">
        <p class="text-sm text-gray-600 mb-3">
            Kolom wajib: <code class="bg-gray-100 px-1">nis</code>, <code class="bg-gray-100 px-1">full_name</code>.
            Opsional: <code class="bg-gray-100 px-1">nisn</code>, <code class="bg-gray-100 px-1">birth_date</code>,
            <code class="bg-gray-100 px-1">gender</code> (male/female), <code class="bg-gray-100 px-1">status</code>.
        </p>

        <form method="POST" action="{{ route('students.import.preview') }}" enctype="multipart/form-data" class="flex gap-2 items-end">
            @csrf
            <input type="file" name="file" required accept=".csv,.txt,.xlsx" class="text-sm">
            <button class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">Unggah &amp; Preview</button>
        </form>
    </div>

    @if ($preview)
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-medium mb-3">Preview — {{ count($preview['rows']) }} baris</h2>

            @php($hasErrors = collect($preview['rows'])->contains(fn ($row) => $row['errors'] !== []))

            <table class="min-w-full text-sm mb-4">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Baris</th>
                        <th class="px-3 py-2">NIS</th>
                        <th class="px-3 py-2">Nama</th>
                        <th class="px-3 py-2">Error</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview['rows'] as $row)
                        <tr class="border-t {{ $row['errors'] !== [] ? 'bg-red-50' : '' }}">
                            <td class="px-3 py-2">{{ $row['line'] }}</td>
                            <td class="px-3 py-2">{{ $row['data']['nis'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ $row['data']['full_name'] ?? '' }}</td>
                            <td class="px-3 py-2 text-red-700">{{ implode(' ', $row['errors']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex gap-2">
                <form method="POST" action="{{ route('students.import.commit') }}">
                    @csrf
                    <button @disabled($hasErrors)
                            class="rounded bg-indigo-600 text-white px-4 py-2 text-sm @if($hasErrors) opacity-50 cursor-not-allowed @endif">
                        Konfirmasi Impor
                    </button>
                </form>
                <form method="POST" action="{{ route('students.import.cancel') }}">
                    @csrf
                    <button class="rounded border border-gray-400 px-4 py-2 text-sm">Batal</button>
                </form>
            </div>

            @if ($hasErrors)
                <p class="mt-2 text-sm text-red-700">Perbaiki baris error pada file lalu unggah ulang. Tidak ada data yang ditulis.</p>
            @endif
        </div>
    @endif
@endsection