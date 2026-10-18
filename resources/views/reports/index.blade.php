@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Laporan Administratif</h1>

    @if (empty($reports))
        <p class="text-gray-500">Anda tidak memiliki akses ke laporan apa pun.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($reports as $key => $report)
                <a href="{{ route('reports.show', $key) }}"
                   class="block bg-white border border-gray-200 rounded p-4 hover:border-indigo-400">
                    <span class="font-medium">{{ $report['label'] }}</span>
                    <span class="block mt-1 text-sm text-gray-500">
                        {{ count($report['columns']) }} kolom · filter periode
                    </span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
