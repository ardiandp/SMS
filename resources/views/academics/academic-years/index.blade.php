@extends('layouts.app')

@section('title', 'Tahun Ajaran')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Tahun Ajaran &amp; Semester</h1>
        @can('create', App\Models\AcademicYear::class)
            <a href="{{ route('academic-years.create') }}" class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">
                + Tambah Tahun Ajaran
            </a>
        @endcan
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Tahun Ajaran</th>
                    <th class="px-4 py-2">Periode</th>
                    <th class="px-4 py-2">Semester</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($years as $year)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $year->name }}</td>
                        <td class="px-4 py-2">
                            {{ $year->starts_on->format('d M Y') }} &mdash; {{ $year->ends_on->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2">{{ $year->semesters->count() }}</td>
                        <td class="px-4 py-2">
                            @if ($year->is_active)
                                <span class="rounded bg-green-100 text-green-800 px-2 py-0.5 text-xs">Aktif</span>
                            @else
                                <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('academic-years.show', $year) }}" class="text-indigo-600 hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                            Belum ada tahun ajaran.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $years->links() }}</div>
@endsection