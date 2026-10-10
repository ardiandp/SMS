@extends('layouts.app')

@section('title', 'Pegawai')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Data Pegawai</h1>
        @can('create', App\Models\Employee::class)
            <a href="{{ route('employees.create') }}" class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">+ Tambah Pegawai</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('employees.index') }}" class="mb-4 flex gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / nomor induk"
               class="w-64 rounded border border-gray-300 px-3 py-1.5 text-sm">
        <button class="rounded bg-gray-800 text-white px-3 py-1.5 text-sm">Cari</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">No. Induk</th>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $employee->employee_number }}</td>
                        <td class="px-4 py-2">{{ $employee->full_name }}</td>
                        <td class="px-4 py-2">{{ $employee->employment_status->label() }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('employees.show', $employee) }}" class="text-indigo-600 hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Tidak ada pegawai.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $employees->links() }}</div>
@endsection