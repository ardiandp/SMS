@extends('layouts.app')

@section('title', 'Surat Sekolah')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Surat Sekolah</h1>
        <a href="{{ route('dashboard') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    @can('create', App\Models\SchoolLetter::class)
        <div class="bg-white rounded shadow p-6 mb-6 text-sm">
            <h2 class="font-semibold mb-3">Buat Draf Surat</h2>

            <form method="POST" action="{{ route('school_letters.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <select name="template_id" class="rounded border border-gray-300 px-2 py-1.5">
                        <option value="">-- tanpa template --</option>
                        @foreach ($templates as $item)
                            <option value="{{ $item->id }}" @selected(old('template_id') == $item->id)>{{ $item->code }} – {{ $item->name }} (v{{ $item->version }})</option>
                        @endforeach
                    </select>

                    <input type="text" name="letter_number" required maxlength="100" placeholder="Nomor surat (unik)"
                           value="{{ old('letter_number') }}" class="w-full rounded border border-gray-300 px-2 py-1.5">
                </div>

                <input type="text" name="subject" required maxlength="200" placeholder="Subjek surat"
                       value="{{ old('subject') }}" class="w-full rounded border border-gray-300 px-2 py-1.5">

                <textarea name="body" rows="4" placeholder="Isi surat (jika tanpa template)"
                          class="w-full rounded border border-gray-300 px-2 py-1.5">{{ old('body') }}</textarea>

                @if ($templates->isNotEmpty())
                    <details class="text-sm">
                        <summary class="font-semibold">Isi dari template (placeholder)</summary>
                        <div class="mt-2 space-y-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="variables[nama]" checked class="rounded">
                                Nama
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="variables[ttd]" checked class="rounded">
                                Tanda Tangan
                            </label>
                        </div>
                    </details>
                @endif

                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Draf</button>
            </form>
        </div>
    @endcan

    <div class="space-y-4">
        @forelse ($letters as $letter)
            <article class="bg-white rounded shadow p-6 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-base">{{ $letter->letter_number }} – {{ $letter->subject }}</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            Status: {{ $letter->status->label() }}
                            @if ($letter->template)
                                · Template: {{ $letter->template->code }} v{{ $letter->template->version }}
                            @endif
                            @if ($letter->issued_at)
                                · Terbit {{ $letter->issued_at->format('d M Y H:i') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        @can('update', $letter)
                            @if ($letter->status->value === 'draft')
                                <a href="{{ route('school_letters.edit', $letter) }}" class="rounded bg-indigo-100 px-3 py-1.5 text-xs text-indigo-700 hover:bg-indigo-200">Ubah</a>
                            @endif
                        @endcan
                        @can('issue', $letter)
                            @if ($letter->status->value === 'draft')
                                <form method="POST" action="{{ route('school_letters.issue', $letter) }}">
                                    @csrf
                                    <button class="rounded bg-green-600 text-white px-3 py-1.5">Terbitkan</button>
                                </form>
                            @endif
                        @endcan
                        @can('cancel', $letter)
                            @if ($letter->status->value === 'issued')
                                <form method="POST" action="{{ route('school_letters.cancel', $letter) }}">
                                    @csrf
                                    <button class="rounded bg-amber-100 px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-200">Batalkan</button>
                                </form>
                            @endif
                        @endcan
                        <a href="{{ route('school_letters.show', $letter) }}" class="rounded border border-gray-400 px-3 py-1.5 text-xs hover:bg-gray-50">Lihat</a>
                    </div>
                </div>
                <div class="prose prose-sm max-w-none mt-2">{!! $letter->body_snapshot !!}</div>
            </article>
        @empty
            <p class="bg-white rounded shadow p-6 text-sm text-center text-gray-500">Belum ada surat.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $letters->links() }}</div>
@endsection
