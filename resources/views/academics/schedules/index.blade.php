@extends('layouts.app')

@section('title', 'Jadwal Pelajaran')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Jadwal Pelajaran — {{ $studyGroup->name }}</h1>
        <a href="{{ route('study-groups.show', $studyGroup) }}"
           class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali ke rombel</a>
    </div>

    @if ($assignments->isEmpty())
        <div class="bg-white rounded shadow p-6 text-sm text-gray-500">
            Tambahkan penugasan guru pada rombel ini terlebih dahulu sebelum membuat jadwal.
        </div>
    @else
        <div class="bg-white rounded shadow p-6 mb-6">
            <h2 class="font-semibold mb-3">Tambah Jadwal</h2>

            <form method="POST" action="{{ route('study-groups.schedules.store', $studyGroup) }}"
                  class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                @csrf

                <select name="teaching_assignment_id" required class="rounded border border-gray-300 px-2 py-1.5">
                    <option value="">-- mapel / guru --</option>
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->id }}">
                            {{ $assignment->subject?->name }} — {{ $assignment->employee?->full_name }}
                        </option>
                    @endforeach
                </select>

                <select name="day_of_week" required class="rounded border border-gray-300 px-2 py-1.5">
                    @foreach (App\Models\ClassSchedule::DAYS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <input type="time" name="starts_at" value="07:00" required
                           class="rounded border border-gray-300 px-2 py-1.5">
                    <input type="time" name="ends_at" value="08:30" required
                           class="rounded border border-gray-300 px-2 py-1.5">
                </div>

                <input type="text" name="room_label" placeholder="Ruang (opsional)" maxlength="100"
                       class="rounded border border-gray-300 px-2 py-1.5">

                <button class="rounded bg-indigo-600 text-white px-3 py-1.5 md:col-span-3">
                    Simpan Jadwal
                </button>
            </form>
        </div>

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">Hari</th>
                        <th class="px-4 py-2">Waktu</th>
                        <th class="px-4 py-2">Mapel</th>
                        <th class="px-4 py-2">Guru</th>
                        <th class="px-4 py-2">Ruang</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $slots = $studyGroup->teachingAssignments->flatMap(
                            fn ($assignment) => $assignment->schedules->map(
                                fn ($schedule) => ['schedule' => $schedule, 'assignment' => $assignment]
                            )
                        )->sortByDesc(fn ($row) => $row['schedule']->id);
                    @endphp

                    @forelse ($slots as $row)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $row['schedule']->dayLabel() }}</td>
                            <td class="px-4 py-2">
                                {{ substr((string) $row['schedule']->starts_at, 0, 5) }} &mdash;
                                {{ substr((string) $row['schedule']->ends_at, 0, 5) }}
                            </td>
                            <td class="px-4 py-2">{{ $row['assignment']->subject?->name }}</td>
                            <td class="px-4 py-2">{{ $row['assignment']->employee?->full_name }}</td>
                            <td class="px-4 py-2">{{ $row['schedule']->room_label ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                @can('delete', $row['schedule'])
                                    <form method="POST" action="{{ route('schedules.destroy', $row['schedule']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline text-xs">Hapus</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                Belum ada jadwal untuk rombel ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection