@extends('layouts.app')
@section('title', 'Detail Kuis')
@section('content')
<div class="container-fluid">
    <h1>{{ $quiz->title }}</h1>
    <div class="card mt-3">
        <div class="card-body">
            <p><strong>Deskripsi:</strong> {{ $quiz->description ?? '-' }}</p>
            <p><strong>Mata Pelajaran:</strong> {{ $quiz->subject->name ?? '-' }}</p>
            <p><strong>Rombel:</strong> {{ $quiz->studyGroup->name ?? '-' }}</p>
            <p><strong>Tahun Ajaran:</strong> {{ $quiz->academicYear->name ?? '-' }}</p>
            <p><strong>Waktu:</strong> {{ $quiz->time_limit ?? '-' }} menit</p>
            <p><strong>Skor Lulus:</strong> {{ $quiz->passing_score }}%</p>
            <p><strong>Acak Soal:</strong> {{ $quiz->shuffle_questions ? 'Ya' : 'Tidak' }}</p>
            <p><strong>Acak Pilihan:</strong> {{ $quiz->shuffle_choices ? 'Ya' : 'Tidak' }}</p>
            <p><strong>Status:</strong> @if($quiz->is_active) Aktif @else Non-Aktif @endif</p>
            <hr>
            <h5>Soal dalam Kuis ini</h5>
            <a href="{{ route('quiz-questions.create') }}?quiz_id={{ $quiz->id }}" class="btn btn-primary mb-3">+ Tambah Soal ke Kuis ini</a>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>#</th><th>Teks Soal</th><th>Tingkat</th><th>Tipe</th><th>Aktif</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($quiz->questions as $question)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $question->question_text }}</td>
                                <td>{{ ucfirst($question->difficulty) }}</td>
                                <td>{{ $question->question_type }}</td>
                                <td>{{ $question->is_active ? 'Aktif' : 'Non-Aktif' }}</td>
                                <td>
                                    <a href="{{ route('quiz-questions.show', ['id' => $question->id]) }}" class="btn btn-sm btn-info">Lihat</a>
                                    <a href="{{ route('quiz-questions.edit', ['id' => $question->id]) }}" class="btn btn-sm btn-warning">Ubah</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center">Belum ada soal dalam kuis ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">Kembali</a>
</div>
@endsection
