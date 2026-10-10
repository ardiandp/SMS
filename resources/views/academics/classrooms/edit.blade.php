@extends('layouts.app')

@section('title', 'Ubah Ruang Kelas')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Ubah Ruang {{ $classroom->name }}</h1>

    @include('academics.classrooms.form', [
        'classroom' => $classroom,
        'action' => route('classrooms.update', $classroom),
        'method' => 'PUT',
    ])

    <a href="{{ route('classrooms.index') }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection