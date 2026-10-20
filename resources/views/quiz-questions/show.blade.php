@extends('layouts.app')
@section('title', 'Detail Soal')
@section('content')
<div class="container-fluid">
    <h1>Detail Soal</h1>
    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Teks Soal</h5>
            <p class="card-text">{{ $question->question_text }}</p>
            <h6>Pilihan Jawaban</h6>
            <ul>
                @foreach($question->choices as $index => $choice)
                    <li>{{ strtoupper($index) }}. {{ $choice }}</li>
                @endforeach
            </ul>
            <p><strong>Tingkat:</strong> {{ ucfirst($question->difficulty) }}</p>
            <p><strong>Tipe:</strong> {{ $question->question_type }}</p>
            <p><strong>Status:</strong> @if($question->is_active) Aktif @else Non-Aktif @endif</p>
            <a href="{{ route('quiz-questions.edit', ['id' => $question->id, 'school_id' => $question->school_id]) }}" class="btn btn-warning">Ubah</a>
            <form action="{{ route('quiz-questions.restore', ['id' => $question->id, 'school_id' => $question->school_id]) }}" method="post" class="d-inline">
                @if($question->is_active)
                    <button class="btn btn-secondary" onclick="return confirm('Nonaktifkan soal ini?')">Nonaktifkan</button>
                @else
                    @csrf
                    <button class="btn btn-success">Aktifkan</button>
                @endif
            </form>
            <form action="{{ route('quiz-questions.destroy', ['id' => $question->id, 'school_id' => $question->school_id]) }}" method="post" class="d-inline">
                @csrf @method('DELETE')
                <button class="btn btn-danger" onclick="return confirm('Hapus soal ini?')">Hapus</button>
            </form>
            <a href="{{ route('quiz-questions.index') }}" class="btn btn-secondary">Kembali</a>
        </div>
    </div>
</div>
@endsection
