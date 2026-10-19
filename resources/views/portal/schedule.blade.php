@extends('layouts.app')

@section('title', 'Jadwal — '.$student->full_name)

@section('content')
    @include('portal._nav')

    <div class="bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-1">Jadwal {{ $student->full_name }}</h1>
        <p class="text-sm text-gray-600">Jadwal mingguan dari rombel aktif.</p>

        @if ($schedules->isEmpty())
            <p class="mt-4 text-gray-500">Belum ada jadwal untuk siswa ini.</p>
        @else
            @foreach ($schedules->groupBy('day_of_week') as $day => $slots)
                <h2 class="mt-4 font-semibold">{{ \App\Models\ClassSchedule::DAYS[$day] ?? '-' }}</h2>
                <ul class="mt-1 divide-y divide-gray-100 text-sm">
                    @foreach ($slots as $slot)
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium">{{ $slot->teachingAssignment?->subject?->name ?? '—' }}</span>
                            <span class="text-gray-600">{{ substr((string) $slot->starts_at, 0, 5) }}–{{ substr((string) $slot->ends_at, 0, 5) }}{{ $slot->room_label ? ' · '.$slot->room_label : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </div>
@endsection
