@extends('layouts.app')

@section('title', 'Absensi — '.$student->full_name)

@section('content')
    @include('portal._nav')

    <div class="bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-1">Absensi {{ $student->full_name }}</h1>
        <p class="text-sm text-gray-600">20 catatan terakhir.</p>

        @if ($records->isEmpty())
            <p class="mt-4 text-gray-500">Belum ada catatan absensi.</p>
        @else
            <ul class="mt-4 divide-y divide-gray-100 text-sm">
                @foreach ($records as $record)
                    <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                        <span>{{ $record->session?->session_date?->format('d M Y') }} · {{ $record->session?->studyGroup?->name ?? '—' }}</span>
                        <span class="font-medium">{{ $record->status->label() }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $records->links() }}</div>
        @endif
    </div>
@endsection
