@extends('layouts.app')

@section('title', 'Template &amp; Surat')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $template->code }} – {{ $template->name }}</h1>
        <a href="{{ route('letter_templates.index') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <div class="bg-white rounded shadow p-6 text-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs text-gray-500">Versi {{ $template->version }} · Aktif: {{ $template->is_active ? 'Ya' : 'Tidak' }} · <small>diperbarui {{ $template->updated_at->format('d M Y H:i') }}</small></p>
                <p class="mt-2 prose prose-sm max-w-none">{!! $template->body_template !!}</p>
            </div>
            <div class="flex gap-2 shrink-0">
                @can('update', $template)
                    <form method="POST" action="{{ route('letter_templates.update', $template) }}" class="flex gap-2">
                        @csrf
                        @method('PUT')
                        <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Revisi</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
@endsection
