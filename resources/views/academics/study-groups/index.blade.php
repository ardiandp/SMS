@extends('layouts.app')

@section('title', 'Rombel')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Rombongan Belajar</h1>
        @can('create', App\Models\StudyGroup::class)
            <a href="{{ route('study-groups.create') }}" class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">
                + Tambah Rombel
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('study-groups.index') }}" class="mb-4 flex gap-2">
        <select name="academic_year_id" class="rounded border border-gray-300 px-3 py-1.5 text-sm">
            <option value="">Semua tahun ajaran</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}" @selected(request('academic_year_id') == $year->id)>{{ $year->name }}</option>
            @endforeach
        </select>
        <select name="grade_level_id" class="rounded border border-gray-300 px-3 py-1.5 text-sm">
            <option value="">Semua kelas</option>
            @foreach ($gradeLevels as $gradeLevel)
                <option value="{{ $gradeLevel->id }}" @selected(request('grade_level_id') == $gradeLevel->id)>{{ $gradeLevel->name }}</option>
            @endforeach
        </select>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5 text-sm">Filter</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Rombel</th>
                    <th class="px-4 py-2">Tahun Ajaran</th>
                    <th class="px-4 py-2">Kelas</th>
                    <th class="px-4 py-2">Wali Kelas</th>
                    <th class="px-4 py-2">Siswa</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $group->name }}</td>
                        <td class="px-4 py-2">{{ $group->academicYear?->name }}</td>
                        <td class="px-4 py-2">{{ $group->gradeLevel?->name }}</td>
                        <td class="px-4 py-2">{{ $group->homeroomTeacher?->full_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $group->members_count }}</td>
                        <td class="px-4 py-2">{{ $group->status->label() }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('study-groups.show', $group) }}" class="text-indigo-600 hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Belum ada rombel.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $groups->links() }}</div>
@endsection