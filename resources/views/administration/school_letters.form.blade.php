@extends('layouts.app')

@section('title', 'Surat Sekolah')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $letter->letter_number }} – {{ $letter->subject }}</h1>
        <a href="{{ route('school_letters.index') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <div class="bg-white rounded shadow p-6 text-sm">
        <p class="text-xs text-gray-500">
            Status: {{ $letter->status->label() }}
            @if ($letter->template)
                · Template: {{ $letter->template->code }} v{{ $letter->template->version }}
            @endif
            @if ($letter->issued_at)
                · Terbit {{ $letter->issued_at->format('d M Y H:i') }}
            @endif
        </p>
        <p class="mt-2 prose prose-sm max-w-none">{!! $letter->body_snapshot !!}</p>
    </div>
@endsection
