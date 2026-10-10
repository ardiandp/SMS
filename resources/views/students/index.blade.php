@extends('layouts.app')

@section('title', 'Siswa')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Data Siswa</h1>
        <div class="flex gap-2">
            <a href="{{ route('students.import.create') }}" class="rounded border border-indigo-600 text-indigo-600 px-3 py-1.5 text-sm hover:bg-indigo-50">Impor CSV/XLSX</a>
            @can('create', App\Models\Student::class)
                <a href="{{ route('students.create') }}" class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">+ Tambah Siswa</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('students.index') }}" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / NIS / NISN"
               class="w-64 rounded border border-gray-300 px-3 py-1.5 text-sm">
        <select name="status" class="rounded border border-gray-300 px-3 py-1.5 text-sm">
            <option value="">Semua status</option>
            @foreach (App\Enums\StudentStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5 text-sm">Cari</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">NIS</th>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">NISN</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $student->nis }}</td>
                        <td class="px-4 py-2">{{ $student->full_name }}</td>
                        <td class="px-4 py-2">{{ $student->nisn ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $student->status->label() }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('students.show', $student) }}" class="text-indigo-600 hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Tidak ada siswa.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $students->links() }}</div>
@endsection