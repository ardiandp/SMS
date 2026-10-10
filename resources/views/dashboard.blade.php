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
@endsection