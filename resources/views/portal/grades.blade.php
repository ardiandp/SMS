@extends('layouts.app')

@section('title', 'Nilai — '.$student->full_name)

@section('content')
    @include('portal._nav')

    <div class="bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-1">Nilai {{ $student->full_name }}</h1>
        <p class="text-sm text-gray-600">Hanya nilai yang sudah difinalisasi sekolah.</p>

        @if ($grades->isEmpty())
            <p class="mt-4 text-gray-500">Belum ada nilai final.</p>
        @else
            <ul class="mt-4 divide-y divide-gray-100 text-sm">
                @foreach ($grades as $grade)
                    <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium">{{ $grade->teachingAssignment?->subject?->name ?? '—' }}</span>
                        <span>{{ $grade->numeric_score }}{{ $grade->grade_label ? ' ('.$grade->grade_label.')' : '' }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $grades->links() }}</div>
        @endif
    </div>
@endsection
