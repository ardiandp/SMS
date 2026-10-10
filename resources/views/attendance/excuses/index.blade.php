@extends('layouts.app')

@section('title', 'Alasan Absensi')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Pengajuan Alasan Absensi</h1>

    <form method="GET" action="{{ route('attendance.excuses.index') }}" class="mb-4 flex gap-2 text-sm">
        <select name="status" class="rounded border border-gray-300 px-2 py-1.5">
            <option value="">Semua status</option>
            @foreach (App\Enums\ExcuseStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Filter</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Siswa</th>
                    <th class="px-4 py-2">Rombel</th>
                    <th class="px-4 py-2">Alasan</th>
                    <th class="px-4 py-2">Bukti</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($excuses as $excuse)
                    <tr class="border-t">
                        <td class="px-4 py-2">
                            {{ $excuse->student?->full_name }}
                            <span class="block text-xs text-gray-500">{{ $excuse->student?->nis }}</span>
                        </td>
                        <td class="px-4 py-2">
                            {{ $excuse->record?->session?->studyGroup?->name }}
                            <span class="block text-xs text-gray-500">
                                {{ $excuse->record?->session?->session_date?->format('d M Y') }}
                            </span>
                        </td>
                        <td class="px-4 py-2 max-w-xs">
                            {{ \Illuminate\Support\Str::limit($excuse->reason, 80) }}
                        </td>
                        <td class="px-4 py-2">
                            @if ($excuse->hasDocument())
                                @can('download', $excuse)
                                    <a href="{{ route('attendance.excuses.download', $excuse) }}"
                                       class="text-indigo-600 hover:underline">unduh</a>
                                @else
                                    <span class="text-xs text-gray-500">ada</span>
                                @endcan
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $excuse->status->label() }}</td>
                        <td class="px-4 py-2">
                            @if ($excuse->status === App\Enums\ExcuseStatus::Pending)
                                @can('review', $excuse)
                                    <div class="flex gap-1">
                                        <form method="POST" action="{{ route('attendance.excuses.review', $excuse) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button class="rounded bg-green-600 text-white px-2 py-0.5 text-xs">Setujui</button>
                                        </form>
                                        <form method="POST" action="{{ route('attendance.excuses.review', $excuse) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="rounded bg-red-600 text-white px-2 py-0.5 text-xs">Tolak</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-500">Menunggu</span>
                                @endcan
                            @else
                                <span class="text-xs text-gray-500">
                                    {{ $excuse->reviewer?->name }}
                                    <span class="block">{{ $excuse->reviewed_at?->format('d M Y') }}</span>
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada pengajuan alasan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $excuses->links() }}</div>
@endsection