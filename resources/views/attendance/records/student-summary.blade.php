@extends('layouts.app')

@section('title', 'Rekap Absensi Siswa')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">
            Rekap {{ $student->full_name }}
            <span class="text-sm font-normal text-gray-500">({{ $student->nis }})</span>
        </h1>
        <a href="{{ route('attendance.reports.index') }}"
           class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <form method="GET" action="{{ route('attendance.records.student', $student) }}" class="mb-4 flex gap-2 text-sm">
        <input type="date" name="from" value="{{ $from->toDateString() }}"
               class="rounded border border-gray-300 px-2 py-1.5">
        <input type="date" name="to" value="{{ $to->toDateString() }}"
               class="rounded border border-gray-300 px-2 py-1.5">
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Terapkan</button>
    </form>

    <div class="bg-white rounded shadow p-4 mb-6 text-sm grid grid-cols-2 md:grid-cols-6 gap-4">
        @foreach (App\Enums\AttendanceStatus::cases() as $status)
            <div>
                <p class="text-gray-500">{{ $status->label() }}</p>
                <p class="text-lg font-semibold">{{ $summary['counts'][$status->value] }}</p>
            </div>
        @endforeach
        <div>
            <p class="text-gray-500">% Hadir</p>
            <p class="text-lg font-semibold">
                {{ $summary['attendance_rate'] === null ? '—' : $summary['attendance_rate'].'%' }}
            </p>
        </div>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">Rombel</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Catatan</th>
                    <th class="px-4 py-2">Koreksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $record->session?->session_date->format('d M Y') }}</td>
                        <td class="px-4 py-2">{{ $record->session?->studyGroup?->name }}</td>
                        <td class="px-4 py-2">{{ $record->status->label() }}</td>
                        <td class="px-4 py-2">{{ $record->notes ?: '—' }}</td>
                        <td class="px-4 py-2 text-xs">
                            @if ($record->wasCorrected())
                                {{ $record->correction_count }}x
                                ({{ $record->previous_status?->label() }} &rarr; {{ $record->status->label() }})
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Tidak ada data pada rentang ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection