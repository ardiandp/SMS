@extends('layouts.app')

@section('title', 'Ubah Tahun Ajaran')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Ubah Tahun Ajaran</h1>

    @include('academics.academic-years.form', [
        'academicYear' => $academicYear,
        'action' => route('academic-years.update', $academicYear),
        'method' => 'PUT',
    ])

    <a href="{{ route('academic-years.show', $academicYear) }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection