@extends('layouts.app')
@section('title', 'Pengumpulan Kuis')
@section('content')
<div class="container-fluid">
    <h1>[[ __('LMS / Pengumpulan Kuis') ]]</h1>
    @if (session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
    <div class="card">
        <div class="card-header">
            <div class="card-actions">
                <a href="{{ route('quiz-submissions.store') }}" class="btn btn-primary">+ Buat Submission</a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Kuis</label>
                    <select name="quiz_id" class="form-select">
                        <option value="0">Semua Kuis</option>
                        @foreach($quizzes as $quiz)
                            <option value="{{ $quiz->id }}" {{ $selectedQuiz == $quiz->id ? 'selected' : '' }}>{{ $quiz->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Siswa</label>
                    <select name="student_id" class="form-select">
                        <option value="0">Semua Siswa</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" {{ $selectedStudent == $student->id ? 'selected' : '' }}>{{ $student->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="submitted" {{ $selectedStatus == 'submitted' ? 'selected' : '' }}>Dikumpulkan</option>
                        <option value="graded" {{ $selectedStatus == 'graded' ? 'selected' : '' }}>Dinilai</option>
                        <option value="draft" {{ $selectedStatus == 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>#</th><th>Kuis</th><th>Siswa</th><th>Skor</th><th>Status</th><th>Dikumpulkan</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($submissions as $submission)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $submission->quiz->title ?? '-' }}</td>
                                <td>{{ $submission->student->full_name ?? '-' }}</td>
                                <td>{{ $submission->score ?? '-' }} / {{ $submission->max_score ?? '-' }}</td>
                                <td>
                                    @switch($submission->status)
                                        @case('draft') <span class="badge bg-secondary">Draft</span> @break
                                        @case('submitted') <span class="badge bg-warning text-dark">Dikumpulkan</span> @break
                                        @case('graded') <span class="badge bg-success">Dinilai</span> @break
                                        @default <span class="badge bg-info">{{ $submission->status }}</span> @break
                                    @endswitch
                                </td>
                                <td>{{ $submission->submitted_at ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('quiz-submissions.show', ['id' => $submission->id]) }}" class="btn btn-sm btn-info">Lihat</a>
                                    @if($submission->status === 'draft')
                                        <a href="{{ route('quiz-submissions.update', ['id' => $submission->id]) }}" class="btn btn-sm btn-warning">Ubah</a>
                                        <form action="{{ route('quiz-submissions.destroy', ['id' => $submission->id]) }}" method="post" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger" onclick="return confirm('Hapus submission ini?')">Hapus</button>
                                        </form>
                                        <form action="{{ route('quiz-submissions.submit', ['id' => $submission->id]) }}" method="post" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-primary" onclick="return confirm('Kumpulkan submission ini?')">Kumpulkan</button>
                                        </form>
                                    @elseif($submission->status === 'submitted')
                                        <form action="{{ route('quiz-submissions.grade', ['id' => $submission->id]) }}" method="post" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success" onclick="return confirm('Nilai submission ini?')">Nilai</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center">Tidak ada submission ditemukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection