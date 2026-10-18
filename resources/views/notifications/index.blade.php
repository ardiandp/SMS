@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Notifikasi</h1>
        <span class="text-sm text-gray-600">{{ $unreadCount }} belum dibaca</span>
    </div>

    @if ($notifications->isEmpty())
        <p class="text-gray-500">Belum ada notifikasi.</p>
    @else
        <ul class="space-y-3">
            @foreach ($notifications as $notification)
                <li class="bg-white border rounded p-4 {{ $notification->read_at === null ? 'border-indigo-300' : 'border-gray-200' }}">
                    <div class="flex items-start justify-between gap-4">
                        <span class="font-medium">{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                        <span class="text-xs text-gray-500 whitespace-nowrap">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>

                    <div class="mt-2 flex items-center gap-4 text-sm">
                        @if (!empty($notification->data['link']))
                            <a href="{{ $notification->data['link'] }}" class="text-indigo-600 hover:underline">Buka</a>
                        @endif

                        @if ($notification->read_at === null)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit" class="text-gray-600 hover:underline">Tandai sudah dibaca</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
@endsection
