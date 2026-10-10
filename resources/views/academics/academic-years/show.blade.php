@extends('layouts.app')

@section('title', 'Detail Tahun Ajaran')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">
            Tahun Ajaran {{ $academicYear->name }}
            @if ($academicYear->is_active)
                <span class="ml-2 rounded bg-green-100 text-green-800 px-2 py-0.5 text-xs">Aktif</span>
            @endif
        </h1>
        <div class="flex gap-2 text-sm">
            @can('update', $academicYear)
                @if ($academicYear->is_active)
                    <form method="POST" action="{{ route('academic-years.deactivate', $academicYear) }}">
                        @csrf
                        <button class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Nonaktifkan</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('academic-years.activate', $academicYear) }}">
                        @csrf
                        <button class="rounded bg-green-600 text-white px-3 py-1.5 hover:bg-green-700">Jadikan Aktif</button>
                    </form>
                @endif
                <a href="{{ route('academic-years.edit', $academicYear) }}"
                   class="rounded border border-indigo-600 text-indigo-600 px-3 py-1.5 hover:bg-indigo-50">Ubah</a>
            @endcan
        </div>
    </div>

    <div class="bg-white rounded shadow p-6 mb-6">
        <p class="text-sm text-gray-600">
            Periode {{ $academicYear->starts_on->format('d M Y') }} &mdash;
            {{ $academicYear->ends_on->format('d M Y') }}
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold mb-3">Semester ({{ $academicYear->semesters->count() }})</h2>

            @forelse ($academicYear->semesters->sortBy('sequence') as $semester)
                <div class="flex justify-between border-t py-2 text-sm">
                    <span>{{ $semester->sequence }}. {{ $semester->name }}</span>
                    <span class="text-gray-500">
                        {{ $semester->starts_on->format('d M Y') }} &mdash; {{ $semester->ends_on->format('d M Y') }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada semester.</p>
            @endforelse

            @can('update', $academicYear)
                <form method="POST" action="{{ route('academic-years.semesters.store', $academicYear) }}"
                      class="mt-4 space-y-2 border-t pt-4 text-sm">
                    @csrf
                    <p class="font-medium">Tambah semester</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="name" placeholder="Nama semester" required
                               class="rounded border border-gray-300 px-2 py-1.5">
                        <input type="number" name="sequence" placeholder="Urutan" min="1" max="20" required
                               class="rounded border border-gray-300 px-2 py-1.5">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="starts_on" required class="rounded border border-gray-300 px-2 py-1.5">
                        <input type="date" name="ends_on" required class="rounded border border-gray-300 px-2 py-1.5">
                    </div>
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Tambah</button>
                </form>
            @endcan
        </div>

        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold mb-3">Rombel ({{ $academicYear->studyGroups->count() }})</h2>

            @forelse ($academicYear->studyGroups as $group)
                <div class="flex justify-between border-t py-2 text-sm">
                    <a href="{{ route('study-groups.show', $group) }}" class="text-indigo-600 hover:underline">
                        {{ $group->name }}
                    </a>
                    <span class="text-gray-500">{{ $group->gradeLevel?->name }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada rombel pada tahun ajaran ini.</p>
            @endforelse
        </div>
    </div>

    <a href="{{ route('academic-years.index') }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection