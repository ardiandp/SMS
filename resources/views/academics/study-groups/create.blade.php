@extends('layouts.app')

@section('title', 'Tambah Rombel')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Tambah Rombel</h1>

    @include('academics.study-groups.form', [
        'studyGroup' => null,
        'action' => route('study-groups.store'),
        'method' => 'POST',
    ])

    <a href="{{ route('study-groups.index') }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection