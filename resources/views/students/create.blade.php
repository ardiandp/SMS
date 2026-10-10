@extends('layouts.app')

@section('title', 'Tambah Siswa')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Tambah Siswa</h1>

    @include('students.form', ['action' => route('students.store'), 'student' => null])
@endsection