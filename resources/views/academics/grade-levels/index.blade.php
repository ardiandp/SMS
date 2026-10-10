@extends('layouts.app')

@section('title', 'Kelas')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Kelas / Jenjang</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold mb-3">Daftar Kelas</h2>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-3 py-2">Kode</th>
                            <th class="px-3 py-2">Nama</th>
                            <th class="px-3 py-2">Jenjang</th>
                            <th class="px-3 py-2">Urutan</th>
                            <th class="px-3 py-2">Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gradeLevels as $gradeLevel)
                            <tr class="border-t">
                                <td class="px-3 py-2">{{ $gradeLevel->code }}</td>
                                <td class="px-3 py-2">{{ $gradeLevel->name }}</td>
                                <td class="px-3 py-2">{{ $gradeLevel->education_level }}</td>
                                <td class="px-3 py-2">{{ $gradeLevel->sequence }}</td>
                                <td class="px-3 py-2">{{ $gradeLevel->is_active ? 'Ya' : 'Tidak' }}</td>
                            </tr>
                        @empty
                            <tr class="border-t">
                                <td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada kelas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $gradeLevels->links() }}</div>
        </div>

        <div class="lg:col-span-2">
            @can('create', App\Models\GradeLevel::class)
                <div class="bg-white rounded shadow p-6">
                    <h2 class="font-semibold mb-3">Tambah Kelas</h2>

                    <form method="POST" action="{{ route('academics.grade-levels.store') }}" class="text-sm space-y-2">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="code" required maxlength="20" placeholder="Kode"
                                   value="{{ old('code') }}" class="rounded border border-gray-300 px-2 py-1.5">
                            <input type="text" name="name" required maxlength="100" placeholder="Nama kelas"
                                   value="{{ old('name') }}" class="rounded border border-gray-300 px-2 py-1.5">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="education_level" required maxlength="50" placeholder="Jenjang (mis. SMP)"
                                   value="{{ old('education_level') }}" class="rounded border border-gray-300 px-2 py-1.5">
                            <input type="number" name="sequence" required min="1" max="99" placeholder="Urutan"
                                   value="{{ old('sequence') }}" class="rounded border border-gray-300 px-2 py-1.5">
                        </div>
                        <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan</button>
                    </form>
                </div>
            @endcan

            <div class="mt-6 space-y-3">
                @foreach ($gradeLevels as $gradeLevel)
                    @can('update', $gradeLevel)
                        <form method="POST" action="{{ route('academics.grade-levels.update', $gradeLevel) }}"
                              class="bg-white rounded shadow p-4 text-sm">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-2">
                                <input type="text" name="code" required maxlength="20" value="{{ $gradeLevel->code }}"
                                       class="rounded border border-gray-300 px-2 py-1.5">
                                <input type="text" name="name" required maxlength="100" value="{{ $gradeLevel->name }}"
                                       class="rounded border border-gray-300 px-2 py-1.5">
                                <input type="text" name="education_level" required maxlength="50"
                                       value="{{ $gradeLevel->education_level }}"
                                       class="rounded border border-gray-300 px-2 py-1.5">
                                <input type="number" name="sequence" required min="1" max="99"
                                       value="{{ $gradeLevel->sequence }}"
                                       class="rounded border border-gray-300 px-2 py-1.5">
                                <label class="flex items-center gap-1">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($gradeLevel->is_active)>
                                    Aktif
                                </label>
                                <button class="rounded bg-gray-800 text-white px-3 py-1.5">Simpan</button>
                            </div>
                        </form>
                    @endcan
                @endforeach
            </div>
        </div>
    </div>
@endsection