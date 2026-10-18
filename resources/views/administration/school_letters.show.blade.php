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

        <div class="mt-4 flex gap-2">
            @can('update', $letter)
                @if ($letter->status->value === 'draft')
                    <a href="{{ route('school_letters.edit', $letter) }}" class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">Ubah</a>
                @endif
            @endcan
            @can('issue', $letter)
                @if ($letter->status->value === 'draft')
                    <form method="POST" action="{{ route('school_letters.issue', $letter) }}" class="inline">
                        @csrf
                        <button class="rounded bg-green-600 text-white px-3 py-1.5 text-sm hover:bg-green-700">Terbitkan</button>
                    </form>
                @endif
            @endcan
            @can('cancel', $letter)
                @if ($letter->status->value === 'issued')
                    <form method="POST" action="{{ route('school_letters.cancel', $letter) }}" onsubmit="return confirm('Batalkan surat ini?')" class="inline">
                        @csrf
                        <button class="rounded bg-amber-100 text-amber-700 px-3 py-1.5 text-sm hover:bg-amber-200">Batalkan</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection