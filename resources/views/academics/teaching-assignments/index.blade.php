@extends('layouts.app')

@section('title', 'Penugasan Guru')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Penugasan Guru</h1>

    <form method="GET" action="{{ route('teaching-assignments.index') }}" class="mb-4">
        <label class="text-sm text-gray-600 mr-2">Rombel</label>
        <select name="study_group_id" class="rounded border border-gray-300 px-3 py-1.5 text-sm">
            <option value="">Semua rombel</option>
            @foreach (App\Models\StudyGroup::orderBy('name')->get() as $group)
                <option value="{{ $group->id }}" @selected(request('study_group_id') == $group->id)>{{ $group->name }}</option>
            @endforeach
        </select>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5 text-sm mt-2">Filter</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Rombel</th>
                    <th class="px-4 py-2">Mata Pelajaran</th>
                    <th class="px-4 py-2">Guru</th>
                    <th class="px-4 py-2">Semester</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    <tr class="border-t">
                        <td class="px-4 py-2">
                            <a href="{{ route('study-groups.show', $assignment->study_group_id) }}"
                               class="text-indigo-600 hover:underline">{{ $assignment->studyGroup?->name }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $assignment->subject?->name }}</td>
                        <td class="px-4 py-2">{{ $assignment->employee?->full_name }}</td>
                        <td class="px-4 py-2">{{ $assignment->semester?->name ?? 'Seluruh tahun' }}</td>
                        <td class="px-4 py-2">{{ $assignment->status->label() }}</td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada penugasan guru.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>
@endsection