@extends('layouts.app')

@section('title', 'Ubah Siswa')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Ubah Siswa</h1>

    @include('students.form', ['action' => route('students.update', $student), 'student' => $student])
@endsection