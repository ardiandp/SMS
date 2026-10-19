@extends('layouts.app')

@section('title', 'Rapor — '.$student->full_name)

@section('content')
    @include('portal._nav')

    <div class="bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-1">Rapor {{ $student->full_name }}</h1>
        <p class="text-sm text-gray-600">Hanya rapor yang sudah diterbitkan sekolah.</p>

        @forelse ($cards as $card)
            <div class="mt-4 rounded border border-gray-200 p-4">
                <div class="font-medium">{{ $card->semester?->academicYear?->name ?? '—' }} · Semester {{ $card->semester?->sequence ?? '—' }}</div>
                <div class="text-xs text-gray-500">Diterbitkan {{ $card->published_at?->format('d M Y') }}</div>

                @if ($card->items->isNotEmpty())
                    <ul class="mt-2 divide-y divide-gray-100 text-sm">
                        @foreach ($card->items as $item)
                            <li class="py-1 flex flex-wrap items-center justify-between gap-2">
                                <span>{{ $item->subject?->name ?? '—' }}</span>
                                <span class="font-medium">{{ $item->final_grade_snapshot_json['numeric_score'] ?? '—' }}{{ ! empty($item->final_grade_snapshot_json['grade_label']) ? ' ('.$item->final_grade_snapshot_json['grade_label'].')' : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="mt-4 text-gray-500">Belum ada rapor yang diterbitkan.</p>
        @endforelse

        <div class="mt-4">{{ $cards->links() }}</div>
    </div>
@endsection
