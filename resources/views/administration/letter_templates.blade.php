@extends('layouts.app')

@section('title', 'Template &amp; Surat')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Template &amp; Surat</h1>
        <a href="{{ route('dashboard') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    @can('create', App\Models\LetterTemplate::class)
        <div class="bg-white rounded shadow p-6 mb-6 text-sm">
            <h2 class="font-semibold mb-3">Buat Template Baru</h2>

            <form method="POST" action="{{ route('letter_templates.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="code" required maxlength="50" placeholder="Kode template (contoh: SURAT-001)"
                       value="{{ old('code') }}" class="w-full rounded border border-gray-300 px-2 py-1.5">
                <input type="text" name="name" required maxlength="200" placeholder="Nama template"
                       value="{{ old('name') }}" class="w-full rounded border border-gray-300 px-2 py-1.5">
                <textarea name="body_template" rows="4" required placeholder="Isi template (boleh placeholder @{{nama_field}})"
                          class="w-full rounded border border-gray-300 px-2 py-1.5">{{ old('body_template') }}</textarea>
                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Template</button>
            </form>
        </div>
    @endcan

    <div class="space-y-4">
        @forelse ($templates as $template)
            <article class="bg-white rounded shadow p-6 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-base">{{ $template->code }} – {{ $template->name }}</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            Versi {{ $template->version }} · Aktif: {{ $template->is_active ? 'Ya' : 'Tidak' }}
                        </p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        @can('update', $template)
                            <a href="{{ route('letter_templates.edit', $template) }}" class="rounded bg-indigo-100 px-3 py-1.5 text-xs text-indigo-700 hover:bg-indigo-200">Revisi</a>
                        @endcan
                        @can('delete', $template)
                            <form method="POST" action="{{ route('letter_templates.destroy', $template) }}" onsubmit="return confirm('Hapus template ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded bg-red-100 px-3 py-1.5 text-xs text-red-700 hover:bg-red-200">Hapus</button>
                            </form>
                        @endcan
                    </div>
                </div>
                <div class="prose prose-sm max-w-none mt-3">{!! $template->body_template !!}</div>
            </article>
        @empty
            <p class="bg-white rounded shadow p-6 text-sm text-center text-gray-500">Belum ada template surat.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $templates->links() }}</div>
@endsection
