@extends('layouts.app')

@section('title', 'Ubah Rombel')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Ubah Rombel {{ $studyGroup->name }}</h1>

    @include('academics.study-groups.form', [
        'studyGroup' => $studyGroup,
        'action' => route('study-groups.update', $studyGroup),
        'method' => 'PUT',
    ])

    <a href="{{ route('study-groups.show', $studyGroup) }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection