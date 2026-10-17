@extends('layouts.app')

@section('title', 'Pengumuman')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Pengumuman</h1>
        <a href="{{ route('dashboard') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    @can('create', App\Models\Announcement::class)
        <div class="bg-white rounded shadow p-6 mb-6 text-sm">
            <h2 class="font-semibold mb-3">Buat Draf Pengumuman</h2>

            <form method="POST" action="{{ route('announcements.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="title" required maxlength="200" placeholder="Judul pengumuman"
                       value="{{ old('title') }}" class="w-full rounded border border-gray-300 px-2 py-1.5">
                <textarea name="body" rows="4" required maxlength="20000" placeholder="Isi pengumuman (format dasar: paragraf, tebal, miring, daftar, tautan)"
                          class="w-full rounded border border-gray-300 px-2 py-1.5">{{ old('body') }}</textarea>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="audiences[0][audience_type]" value="all" checked class="rounded">
                        Seluruh sekolah
                    </label>
                    <input type="hidden" name="audiences[0][audience_id]" value="">
                </div>
                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Draf</button>
            </form>

            <p class="mt-2 text-xs text-gray-500">
                Draf tersimpan dengan status draf. Terbitkan setelah memeriksa isi dan audience;
                pengumuman terbit tidak dapat diubah, hanya dapat diarsipkan.
            </p>
        </div>
    @endcan

    <div class="space-y-4">
        @forelse ($announcements as $announcement)
            <article class="bg-white rounded shadow p-6 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-base">{{ $announcement->title }}</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            Status: {{ $announcement->status->label() }}
                            @if ($announcement->published_at)
                                · Terbit {{ $announcement->published_at->format('d M Y H:i') }}
                            @endif
                            · Audience:
                            @foreach ($announcement->recipients as $recipient)
                                <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ $recipient->audience_type }}</span>
                            @endforeach
                        </p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        @can('publish', $announcement)
                            @if ($announcement->status->value === 'draft')
                                <form method="POST" action="{{ route('announcements.publish', $announcement) }}">
                                    @csrf
                                    <button class="rounded bg-green-600 text-white px-3 py-1.5">Terbitkan</button>
                                </form>
                            @endif
                        @endcan
                        @can('archive', $announcement)
                            @if ($announcement->status->value === 'published')
                                <form method="POST" action="{{ route('announcements.archive', $announcement) }}">
                                    @csrf
                                    <button class="rounded bg-gray-600 text-white px-3 py-1.5">Arsipkan</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
                <div class="prose prose-sm max-w-none mt-3">{!! $announcement->body !!}</div>
            </article>
        @empty
            <p class="bg-white rounded shadow p-6 text-sm text-center text-gray-500">Belum ada pengumuman.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $announcements->links() }}</div>
@endsection
