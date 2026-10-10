@extends('layouts.app')

@section('title', 'Absensi')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Sesi Absensi</h1>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('attendance.reports.index') }}"
               class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Rekap</a>
            @can('viewAny', App\Models\AttendanceExcuse::class)
                <a href="{{ route('attendance.excuses.index') }}"
                   class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Alasan</a>
            @endcan
        </div>
    </div>

    @can('create', App\Models\AttendanceSession::class)
        <div class="bg-white rounded shadow p-6 mb-6">
            <h2 class="font-semibold mb-3">Buka Sesi Baru</h2>

            <form method="POST" action="{{ route('attendance.sessions.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                @csrf
                <select name="study_group_id" required class="rounded border border-gray-300 px-2 py-1.5">
                    <option value="">-- pilih rombel --</option>
                    @foreach ($studyGroups as $group)
                        <option value="{{ $group->id }}" @selected(old('study_group_id') == $group->id)>
                            {{ $group->name }} ({{ $group->academicYear?->name }})
                        </option>
                    @endforeach
                </select>
                <input type="date" name="session_date" value="{{ old('session_date', now()->format('Y-m-d')) }}"
                       required class="rounded border border-gray-300 px-2 py-1.5">
                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Buka Sesi</button>
            </form>

            <p class="mt-2 text-xs text-gray-500">
                Absensi dicatat satu kali per rombel per hari. Satu sesi hanya bisa dibuka satu kali.
            </p>
        </div>
    @endcan

    <form method="GET" action="{{ route('attendance.sessions.index') }}" class="mb-4 flex gap-2 text-sm">
        <select name="study_group_id" class="rounded border border-gray-300 px-2 py-1.5">
            <option value="">Semua rombel</option>
            @foreach ($studyGroups as $group)
                <option value="{{ $group->id }}" @selected(request('study_group_id') == $group->id)>{{ $group->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ request('date') }}" class="rounded border border-gray-300 px-2 py-1.5">
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Filter</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">Rombel</th>
                    <th class="px-4 py-2">Jumlah Catatan</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $session->session_date->format('d M Y') }}</td>
                        <td class="px-4 py-2">{{ $session->studyGroup?->name }}</td>
                        <td class="px-4 py-2">{{ $session->records_count }}</td>
                        <td class="px-4 py-2">
                            @if ($session->status === \App\Enums\AttendanceSessionStatus::Finalized)
                                <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Selesai</span>
                            @else
                                <span class="rounded bg-green-100 text-green-800 px-2 py-0.5 text-xs">Berlangsung</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('attendance.sessions.show', $session) }}"
                               class="text-indigo-600 hover:underline">Isi Absensi</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada sesi absensi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
@endsection