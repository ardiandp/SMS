@extends('layouts.app')

@section('title', 'Tambah Tahun Ajaran')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Tambah Tahun Ajaran</h1>

    @include('academics.academic-years.form', ['academicYear' => null, 'action' => route('academic-years.store'), 'method' => 'POST'])

    <a href="{{ route('academic-years.index') }}" class="mt-4 inline-block text-sm text-gray-600 hover:underline">
        &larr; Kembali
    </a>
@endsection