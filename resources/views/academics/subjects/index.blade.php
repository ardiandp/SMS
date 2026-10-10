@extends('layouts.app')

@section('title', 'Mata Pelajaran')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Mata Pelajaran</h1>

    @can('create', App\Models\Subject::class)
        <div class="bg-white rounded shadow p-6 mb-6">
            <h2 class="font-semibold mb-3">Tambah Mata Pelajaran</h2>

            <form method="POST" action="{{ route('academics.subjects.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                @csrf
                <input type="text" name="code" required maxlength="30" placeholder="Kode" value="{{ old('code') }}"
                       class="rounded border border-gray-300 px-2 py-1.5">
                <input type="text" name="name" required maxlength="150" placeholder="Nama" value="{{ old('name') }}"
                       class="rounded border border-gray-300 px-2 py-1.5">
                <input type="text" name="category" maxlength="50" placeholder="Kategori" value="{{ old('category') }}"
                       class="rounded border border-gray-300 px-2 py-1.5">
                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan</button>
            </form>
        </div>
    @endcan

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Kode</th>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Kategori</th>
                    <th class="px-4 py-2">Penugasan</th>
                    <th class="px-4 py-2">Aktif</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $subject)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $subject->code }}</td>
                        <td class="px-4 py-2">{{ $subject->name }}</td>
                        <td class="px-4 py-2">{{ $subject->category ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $subject->teaching_assignments_count }}</td>
                        <td class="px-4 py-2">{{ $subject->is_active ? 'Ya' : 'Tidak' }}</td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada mata pelajaran.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $subjects->links() }}</div>

    <div class="mt-6 space-y-3">
        @foreach ($subjects as $subject)
            @can('update', $subject)
                <form method="POST" action="{{ route('academics.subjects.update', $subject) }}"
                      class="bg-white rounded shadow p-4 text-sm">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                        <input type="text" name="code" required maxlength="30" value="{{ $subject->code }}"
                               class="rounded border border-gray-300 px-2 py-1.5">
                        <input type="text" name="name" required maxlength="150" value="{{ $subject->name }}"
                               class="rounded border border-gray-300 px-2 py-1.5">
                        <input type="text" name="category" maxlength="50" value="{{ $subject->category }}"
                               class="rounded border border-gray-300 px-2 py-1.5">
                        <label class="flex items-center gap-1">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($subject->is_active)>
                            Aktif
                        </label>
                        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Simpan</button>
                    </div>
                </form>
            @endcan
        @endforeach
    </div>
@endsection