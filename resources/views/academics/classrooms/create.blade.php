@extends('layouts.app')

@section('title', 'Tambah Ruang Kelas')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Tambah Ruang Kelas</h1>

    @include('academics.classrooms.form', [
        'classroom' => null,
        'action' => route('classrooms.store'),
        'method' => 'POST',
    ])

    <a href="{{ route('classrooms.index') }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection