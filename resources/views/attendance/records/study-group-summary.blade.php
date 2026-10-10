@extends('layouts.app')

@section('title', 'Rekap Rombel')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Rekap {{ $studyGroup->name }}</h1>
        <a href="{{ route('attendance.reports.index') }}"
           class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <form method="GET" action="{{ route('attendance.records.group', $studyGroup) }}" class="mb-4 flex gap-2 text-sm">
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
                    @foreach (App\Enums\AttendanceStatus::cases() as $status)
                        <th class="px-4 py-2">{{ $status->label() }}</th>
                    @endforeach
                    <th class="px-4 py-2">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daily as $date => $row)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</td>
                        @foreach (App\Enums\AttendanceStatus::cases() as $status)
                            <td class="px-4 py-2">{{ $row[$status->value] ?? 0 }}</td>
                        @endforeach
                        <td class="px-4 py-2">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Tidak ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection