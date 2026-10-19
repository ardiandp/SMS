@extends('layouts.app')

@section('title', 'Pengumuman — '.$student->full_name)

@section('content')
    @include('portal._nav')

    <div class="bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-1">Pengumuman</h1>
        <p class="text-sm text-gray-600">Pengumuman terbit yang relevan untuk akun ini.</p>

        @if ($announcements->isEmpty())
            <p class="mt-4 text-gray-500">Belum ada pengumuman.</p>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($announcements as $announcement)
                    <li class="rounded border border-gray-200 p-4">
                        <div class="font-medium">{{ $announcement->title }}</div>
                        <div class="text-xs text-gray-500">{{ $announcement->published_at?->format('d M Y') }}</div>
                        <p class="mt-2 text-sm text-gray-700">{{ $announcement->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
