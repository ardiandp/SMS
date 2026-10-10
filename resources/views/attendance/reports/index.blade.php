@extends('layouts.app')

@section('title', 'Rekap Absensi')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Rekap Absensi</h1>
        @can('export', App\Models\AttendanceRecord::class)
            <a href="{{ route('attendance.reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
               class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">Ekspor CSV</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('attendance.reports.index') }}" class="mb-4 flex gap-2 text-sm">
        <input type="date" name="from" value="{{ $from->toDateString() }}"
               class="rounded border border-gray-300 px-2 py-1.5">
        <input type="date" name="to" value="{{ $to->toDateString() }}"
               class="rounded border border-gray-300 px-2 py-1.5">
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Terapkan</button>
    </form>

    <h2 class="font-semibold mb-2">Per Rombel</h2>

    <div class="bg-white rounded shadow overflow-x-auto mb-6">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Rombel</th>
                    @foreach (App\Enums\AttendanceStatus::cases() as $status)
                        <th class="px-4 py-2">{{ $status->label() }}</th>
                    @endforeach
                    <th class="px-4 py-2">Total</th>
                    <th class="px-4 py-2">% Hadir</th>
                    <th class="px-4 py-2">% Efektif</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byGroup as $group)
                    <tr class="border-t">
                        <td class="px-4 py-2">
                            <a href="{{ route('attendance.records.group', $group['study_group_id']) }}"
                               class="text-indigo-600 hover:underline">{{ $group['study_group'] }}</a>
                        </td>
                        @foreach (App\Enums\AttendanceStatus::cases() as $status)
                            <td class="px-4 py-2">{{ $group['counts'][$status->value] }}</td>
                        @endforeach
                        <td class="px-4 py-2">{{ $group['total'] }}</td>
                        <td class="px-4 py-2">
                            {{ $group['attendance_rate'] === null ? '—' : $group['attendance_rate'].'%' }}
                        </td>
                        <td class="px-4 py-2">
                            {{ $group['effective_attendance_rate'] === null ? '—' : $group['effective_attendance_rate'].'%' }}
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            Tidak ada data absensi pada rentang ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="font-semibold mb-2">Per Tanggal</h2>

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
                @forelse ($byDate as $date => $row)
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

    <p class="mt-4 text-xs text-gray-600">
        <strong>% Hadir</strong> = hadir &divide; total.
        <strong>% Efektif</strong> = (hadir + sakit + izin) &divide; total.
        Periode tanpa catatan menampilkan &ldquo;&mdash;&rdquo;, bukan 0%, agar &ldquo;tidak ada data&rdquo;
        tidak tertukar dengan &ldquo;tidak ada yang hadir&rdquo;.
    </p>
@endsection