@extends('layouts.app')

@section('title', 'Isi Absensi')

@section('content')
    @php
        $summary = app(\App\Domain\Attendance\AttendanceReportService::class)->forSession($session);
        $canMark = auth()->user()->can('update', $session) && ! $session->isLocked();
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">
            Absensi {{ $session->studyGroup?->name }} — {{ $session->session_date->format('d M Y') }}
            @if ($session->isLocked())
                <span class="ml-2 rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Selesai</span>
            @endif
        </h1>
        <a href="{{ route('attendance.sessions.index') }}"
           class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <div class="bg-white rounded shadow p-4 mb-6 text-sm grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach (App\Enums\AttendanceStatus::cases() as $status)
            <div>
                <p class="text-gray-500">{{ $status->label() }}</p>
                <p class="text-lg font-semibold">{{ $summary['counts'][$status->value] }}</p>
            </div>
        @endforeach
        <div>
            <p class="text-gray-500">Total</p>
            <p class="text-lg font-semibold">{{ $summary['total'] }}</p>
        </div>
    </div>

    @if ($session->isLocked())
        <div class="mb-4 rounded bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            Sesi ini sudah ditutup. Setiap perubahan dicatat sebagai koreksi lengkap dengan alasan dan pelaku.
        </div>
    @endif

    @if ($canMark)
        <div class="bg-white rounded shadow p-4 mb-4 text-sm">
            <form method="POST" action="{{ route('attendance.sessions.mark-all', $session) }}"
                  class="flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <p class="font-medium mb-1">Tandai semua</p>
                    <select name="status" class="rounded border border-gray-300 px-2 py-1.5">
                        @foreach (App\Enums\AttendanceStatus::options() as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="text" name="notes" maxlength="255" placeholder="Catatan (opsional)"
                       class="rounded border border-gray-300 px-2 py-1.5">
                <button class="rounded bg-gray-800 text-white px-3 py-1.5">Terapkan</button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('attendance.sessions.mark', $session) }}">
        @csrf
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">NIS</th>
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Alasan</th>
                        <th class="px-4 py-2">Koreksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $record->student?->nis }}</td>
                            <td class="px-4 py-2">{{ $record->student?->full_name }}</td>
                            <td class="px-4 py-2">
                                @if ($canMark)
                                    <select name="statuses[{{ $record->id }}]"
                                            class="rounded border border-gray-300 px-2 py-1 text-sm">
                                        @foreach (App\Enums\AttendanceStatus::cases() as $status)
                                            <option value="{{ $status->value }}"
                                                @selected($record->status === $status)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span>{{ $record->status->label() }}</span>
                                    @if ($record->notes)
                                        <span class="block text-xs text-gray-500">{{ $record->notes }}</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($record->excuse)
                                    <span class="text-xs">{{ $record->excuse->status->label() }}</span>
                                    <span class="block text-xs text-gray-500">
                                        {{ \Illuminate\Support\Str::limit($record->excuse->reason, 40) }}
                                    </span>
                                    @if ($record->excuse->hasDocument() && auth()->user()->can('download', $record->excuse))
                                        <a href="{{ route('attendance.excuses.download', $record->excuse) }}"
                                           class="text-xs text-indigo-600 hover:underline">unduh bukti</a>
                                    @endif
                                @elseif (in_array($record->status, App\Enums\AttendanceStatus::requireExcuse(), true))
                                    <button type="button"
                                            onclick="document.getElementById('excuse-{{ $record->id }}').classList.toggle('hidden')"
                                            class="text-xs text-indigo-600 hover:underline">+ alasan</button>
                                    <div id="excuse-{{ $record->id }}" class="hidden mt-2">
                                        <input type="text" name="reason" required maxlength="500" placeholder="Alasan"
                                               form="excuse-form-{{ $record->id }}"
                                               class="w-full rounded border border-gray-300 px-2 py-1 text-xs">
                                        <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf"
                                               form="excuse-form-{{ $record->id }}"
                                               class="w-full text-xs mt-1">
                                        <button type="submit" form="excuse-form-{{ $record->id }}"
                                                class="rounded bg-indigo-600 text-white px-2 py-1 text-xs mt-1">Kirim</button>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs">
                                @if ($record->wasCorrected())
                                    <span class="text-amber-700">{{ $record->correction_count }}x</span>
                                    <span class="block text-gray-500">
                                        {{ $record->previous_status?->label() }} &rarr; {{ $record->status->label() }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif

                                @if ($session->isLocked() && auth()->user()->can('correct', $record))
                                    <button type="button"
                                            onclick="document.getElementById('correct-{{ $record->id }}').classList.toggle('hidden')"
                                            class="block text-indigo-600 hover:underline">koreksi</button>
                                    <div id="correct-{{ $record->id }}" class="hidden mt-1 space-y-1">
                                        <select name="status" form="correct-form-{{ $record->id }}"
                                                class="rounded border border-gray-300 px-1 py-0.5 text-xs">
                                            @foreach (App\Enums\AttendanceStatus::cases() as $status)
                                                <option value="{{ $status->value }}"
                                                    @selected($record->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="reason" required maxlength="255"
                                               placeholder="Alasan koreksi" form="correct-form-{{ $record->id }}"
                                               class="w-full rounded border border-gray-300 px-1 py-0.5 text-xs">
                                        <button type="submit" form="correct-form-{{ $record->id }}"
                                                class="rounded bg-amber-600 text-white px-2 py-0.5 text-xs">Simpan</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                Tidak ada siswa di rombel ini pada tanggal tersebut.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canMark)
            <div class="mt-4 flex flex-wrap items-end gap-2 text-sm">
                <div>
                    <p class="font-medium mb-1">Catatan umum</p>
                    <input type="text" name="notes" maxlength="255"
                           class="rounded border border-gray-300 px-2 py-1.5">
                </div>
                <button class="rounded bg-indigo-600 text-white px-4 py-1.5">Simpan Absensi</button>
            </div>
        @endif
    </form>

    {{-- Form koreksi dan alasan berada di luar form utama; input-nya menuju ke
         sini memakai atribut HTML5 `form="..."`, karena nested form tidak valid HTML. --}}
    @foreach ($records as $record)
        @can('correct', $record)
            <form id="correct-form-{{ $record->id }}" method="POST" class="hidden"
                  action="{{ route('attendance.records.correct', $record) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
            </form>
        @endcan

        @if (in_array($record->status, App\Enums\AttendanceStatus::requireExcuse(), true) && ! $record->excuse)
            <form id="excuse-form-{{ $record->id }}" method="POST" class="hidden"
                  action="{{ route('attendance.excuses.store', $record) }}" enctype="multipart/form-data">
                @csrf
            </form>
        @endif
    @endforeach

    @can('update', $session)
        @php($roster = app(\App\Domain\Attendance\StudyGroupRoster::class)->studentsOn($session->studyGroup, $session->session_date->toDateString()))
        @if ($roster->isNotEmpty())
            <div class="mt-6 bg-white rounded shadow p-4 text-sm">
                <form method="POST" action="{{ route('attendance.sessions.students.store', $session) }}"
                      class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <p class="font-medium mb-1">Tambah siswa terlambat</p>
                        <select name="student_id" required class="rounded border border-gray-300 px-2 py-1.5">
                            @foreach ($roster as $student)
                                @continue($records->contains('student_id', $student->id))
                                <option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->nis }})</option>
                            @endforeach
                        </select>
                    </div>
                    <select name="status" class="rounded border border-gray-300 px-2 py-1.5">
                        @foreach (App\Enums\AttendanceStatus::options() as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Tambahkan</button>
                </form>
            </div>
        @endif
    @endcan

    @can('finalize', $session)
        <div class="mt-6 bg-white rounded shadow p-4 text-sm">
            @if ($session->isLocked())
                <form method="POST" action="{{ route('attendance.sessions.reopen', $session) }}"
                      class="flex items-end gap-2">
                    @csrf
                    <input type="text" name="reason" required maxlength="255" placeholder="Alasan membuka kembali"
                           class="rounded border border-gray-300 px-2 py-1.5">
                    <button class="rounded border border-gray-500 px-3 py-1.5">Buka Kembali</button>
                </form>
            @else
                <form method="POST" action="{{ route('attendance.sessions.finalize', $session) }}">
                    @csrf
                    <button class="rounded bg-green-600 text-white px-4 py-1.5">Tutup Sesi</button>
                </form>
            @endif
        </div>
    @endcan
@endsection