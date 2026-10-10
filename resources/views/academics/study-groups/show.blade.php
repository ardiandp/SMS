@extends('layouts.app')

@section('title', 'Detail Rombel')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Rombel {{ $studyGroup->name }}</h1>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('study-groups.schedules.index', $studyGroup) }}"
               class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Jadwal</a>
            @can('update', $studyGroup)
                <a href="{{ route('study-groups.edit', $studyGroup) }}"
                   class="rounded border border-indigo-600 text-indigo-600 px-3 py-1.5 hover:bg-indigo-50">Ubah</a>
            @endcan
        </div>
    </div>

    <div class="bg-white rounded shadow p-6 mb-6 text-sm grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <p class="text-gray-500">Tahun Ajaran</p>
            <p class="font-medium">{{ $studyGroup->academicYear?->name }}</p>
        </div>
        <div>
            <p class="text-gray-500">Kelas</p>
            <p class="font-medium">{{ $studyGroup->gradeLevel?->name }}</p>
        </div>
        <div>
            <p class="text-gray-500">Wali Kelas</p>
            <p class="font-medium">{{ $studyGroup->homeroomTeacher?->full_name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-gray-500">Ruang</p>
            <p class="font-medium">{{ $studyGroup->classroom?->name ?? '—' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold mb-3">Siswa Aktif ({{ $studyGroup->members->count() }})</h2>

            @forelse ($studyGroup->members as $member)
                <div class="flex justify-between border-t py-2 text-sm">
                    <span>
                        {{ $member->student?->full_name }}
                        <span class="text-gray-400 text-xs">{{ $member->student?->nis }}</span>
                    </span>
                    @can('manageMembers', $studyGroup)
                        <form method="POST" action="{{ route('study-groups.students.destroy', [$studyGroup, $member->student]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline text-xs">Keluarkan</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada siswa di rombel ini.</p>
            @endforelse

            @can('manageMembers', $studyGroup)
                <form method="POST" action="{{ route('study-groups.students.store', $studyGroup) }}"
                      class="mt-4 border-t pt-4 text-sm space-y-2">
                    @csrf
                    <p class="font-medium">Masukkan siswa</p>
                    <select name="student_id" required class="w-full rounded border border-gray-300 px-2 py-1.5">
                        <option value="">-- pilih siswa --</option>
                        @foreach ($availableStudents as $student)
                            <option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->nis }})</option>
                        @endforeach
                    </select>
                    <input type="date" name="starts_on" value="{{ now()->format('Y-m-d') }}"
                           class="rounded border border-gray-300 px-2 py-1.5">
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Masukkan</button>
                    <p class="text-xs text-gray-500">
                        Memindahkan siswa akan menutup penempatan aktif sebelumnya, bukan menghapus riwayatnya.
                    </p>
                </form>
            @endcan
        </div>

        <div class="bg-white rounded shadow p-6">
            <h2 class="font-semibold mb-3">Penugasan Guru ({{ $studyGroup->teachingAssignments->count() }})</h2>

            @forelse ($studyGroup->teachingAssignments as $assignment)
                <div class="flex justify-between border-t py-2 text-sm">
                    <span>
                        {{ $assignment->subject?->name }}
                        <span class="text-gray-400 text-xs">— {{ $assignment->employee?->full_name }}</span>
                    </span>
                    @can('delete', $assignment)
                        <form method="POST" action="{{ route('teaching-assignments.destroy', $assignment) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline text-xs">Hapus</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada penugasan guru.</p>
            @endforelse

            @can('create', App\Models\TeachingAssignment::class)
                <form method="POST" action="{{ route('teaching-assignments.store') }}"
                      class="mt-4 border-t pt-4 text-sm space-y-2">
                    @csrf
                    <p class="font-medium">Tambah penugasan</p>
                    <input type="hidden" name="study_group_id" value="{{ $studyGroup->id }}">

                    <select name="subject_id" required class="w-full rounded border border-gray-300 px-2 py-1.5">
                        <option value="">-- mata pelajaran --</option>
                        @foreach (App\Models\Subject::orderBy('name')->get() as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>

                    <select name="employee_id" required class="w-full rounded border border-gray-300 px-2 py-1.5">
                        <option value="">-- guru --</option>
                        @foreach (App\Models\Employee::orderBy('full_name')->get() as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>

                    <select name="semester_id" class="w-full rounded border border-gray-300 px-2 py-1.5">
                        <option value="">-- seluruh tahun --</option>
                        @foreach ($studyGroup->academicYear?->semesters ?? [] as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                        @endforeach
                    </select>

                    <input type="hidden" name="status" value="active">

                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Penugasan</button>
                </form>
            @endcan
        </div>
    </div>

    <a href="{{ route('study-groups.index') }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection