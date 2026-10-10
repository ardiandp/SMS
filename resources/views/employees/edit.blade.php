@extends('layouts.app')

@section('title', 'Ubah Pegawai')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Ubah Pegawai</h1>
    @include('employees.form', ['employee' => $employee])
@endsection