@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-1">Dashboard</h1>
        <p class="text-gray-700">Sekolah aktif: <span class="font-medium">{{ $school?->name }}</span></p>

        @if ($roles->isNotEmpty())
            <p class="mt-2 text-sm text-gray-600">Peran Anda: {{ $roles->implode(', ') }}</p>
        @endif

        <p class="mt-4 text-sm text-gray-500">
            Modul akademik menyusul pada tahap PRD berikutnya.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div class="bg-white rounded shadow p-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold">Pengumuman Terbaru</h2>
                <a href="{{ route('announcements.index') }}" class="text-sm text-indigo-600 hover:underline">Semua</a>
            </div>
            @forelse ($announcements as $announcement)
                <div class="border-t border-gray-100 py-2 text-sm">
                    <div class="font-medium">{{ $announcement->title }}</div>
                    <div class="text-xs text-gray-500">{{ $announcement->published_at?->format('d M Y') }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada pengumuman.</p>
            @endforelse
        </div>

        <div class="bg-white rounded shadow p-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold">Kegiatan Mendatang</h2>
                <a href="{{ route('academic-events.index') }}" class="text-sm text-indigo-600 hover:underline">Kalender</a>
            </div>
            @forelse ($upcomingEvents as $event)
                <div class="border-t border-gray-100 py-2 text-sm">
                    <div class="font-medium">{{ $event->title }}</div>
                    <div class="text-xs text-gray-500">{{ $event->starts_at->format('d M Y H:i') }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada kegiatan mendatang.</p>
            @endforelse
        </div>
    </div>
@endsection