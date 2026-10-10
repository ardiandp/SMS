@extends('layouts.app')

@section('title', 'Kategori Asesmen')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Kategori Asesmen</h1>
        <a href="{{ route('assessments.index') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    @can('create', App\Models\AssessmentCategory::class)
        <div class="bg-white rounded shadow p-6 mb-6">
            <h2 class="font-semibold mb-3">Tambah Kategori</h2>

            <form method="POST" action="{{ route('assessments.categories.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                @csrf
                <input type="text" name="name" required maxlength="100" placeholder="Nama (mis. Tugas)" value="{{ old('name') }}"
                       class="rounded border border-gray-300 px-2 py-1.5">
                <input type="text" name="slug" required maxlength="50" placeholder="Slug (mis. tugas)" value="{{ old('slug') }}"
                       pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                       class="rounded border border-gray-300 px-2 py-1.5">
                <input type="number" step="0.01" min="0" max="100" name="default_weight" placeholder="Bobot default (%)"
                       value="{{ old('default_weight') }}"
                       class="rounded border border-gray-300 px-2 py-1.5">
                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan</button>
            </form>

            <p class="mt-2 text-xs text-gray-500">
                Slug dirujuk oleh kebijakan penilaian dan tidak boleh berubah setelah dipakai.
            </p>
        </div>
    @endcan

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Slug</th>
                    <th class="px-4 py-2">Bobot Default</th>
                    <th class="px-4 py-2">Asesmen</th>
                    <th class="px-4 py-2">Aktif</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $category->name }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $category->slug }}</td>
                        <td class="px-4 py-2">{{ $category->default_weight ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $category->assessments_count }}</td>
                        <td class="px-4 py-2">{{ $category->is_active ? 'Ya' : 'Tidak' }}</td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada kategori.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 space-y-3">
        @foreach ($categories as $category)
            @can('update', $category)
                <form method="POST" action="{{ route('assessments.categories.update', $category) }}"
                      class="bg-white rounded shadow p-4 text-sm">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                        <input type="text" name="name" required maxlength="100" value="{{ $category->name }}"
                               class="rounded border border-gray-300 px-2 py-1.5">
                        <input type="text" name="slug" required maxlength="50" value="{{ $category->slug }}"
                               pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                               class="rounded border border-gray-300 px-2 py-1.5">
                        <input type="number" step="0.01" min="0" max="100" name="default_weight"
                               value="{{ $category->default_weight }}"
                               class="rounded border border-gray-300 px-2 py-1.5">
                        <label class="flex items-center gap-1">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($category->is_active)>
                            Aktif
                        </label>
                        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Simpan</button>
                    </div>
                </form>
            @endcan
        @endforeach
    </div>
@endsection