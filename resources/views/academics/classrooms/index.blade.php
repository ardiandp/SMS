@extends('layouts.app')

@section('title', 'Ruang Kelas')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Ruang Kelas</h1>
        @can('create', App\Models\Classroom::class)
            <a href="{{ route('classrooms.create') }}"
               class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">+ Tambah Ruang</a>
        @endcan
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Gedung</th>
                    <th class="px-4 py-2">Kapasitas</th>
                    <th class="px-4 py-2">Rombel</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classrooms as $classroom)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $classroom->name }}</td>
                        <td class="px-4 py-2">{{ $classroom->building ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $classroom->capacity ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $classroom->study_groups_count }}</td>
                        <td class="px-4 py-2">{{ $classroom->status->label() }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $classroom)
                                <a href="{{ route('classrooms.edit', $classroom) }}"
                                   class="text-indigo-600 hover:underline">Ubah</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada ruang kelas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $classrooms->links() }}</div>
@endsection