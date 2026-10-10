@extends('layouts.app')

@section('title', 'Wali')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Data Wali</h1>
        @can('create', App\Models\Guardian::class)
            <a href="{{ route('guardians.create') }}" class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">+ Tambah Wali</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('guardians.index') }}" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / telepon"
               class="w-64 rounded border border-gray-300 px-3 py-1.5 text-sm">
        <button class="rounded bg-gray-800 text-white px-3 py-1.5 text-sm">Cari</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Telepon</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($guardians as $guardian)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $guardian->full_name }}</td>
                        <td class="px-4 py-2">{{ $guardian->phone ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $guardian->email ?: '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $guardian)
                                <a href="{{ route('guardians.edit', $guardian) }}" class="text-indigo-600 hover:underline">Ubah</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Tidak ada wali.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $guardians->links() }}</div>
@endsection