@extends('layouts.app')

@section('title', 'Ubah Wali')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Ubah Wali</h1>
    @include('guardians.form', ['guardian' => $guardian])
@endsection