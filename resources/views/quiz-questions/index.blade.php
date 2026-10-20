@extends('layouts.app')
@section('title', 'Soal LMS')
@section('content')
<div class="container-fluid">
    <h1>[[ __('LMS / Soal') ]]</h1>
    @if (session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
    <div class="card">
        <div class="card-header">
            <div class="card-actions">
                <a href="{{ route('quiz-questions.create') }}" class="btn btn-primary">+ Tambah Soal</a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Tahun Ajaran</label>
                    <select name="academic_year_id" class="form-select">
                        <option value="0">Semua Tahun Ajaran</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ $selectedAcademicYear == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="subject_id" class="form-select">
                        <option value="0">Semua Mata Pelajaran</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" {{ $selectedSubject == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Rombel</label>
                    <select name="study_group_id" class="form-select">
                        <option value="0">Semua Rombel</option>
                        @foreach($studyGroups as $group)
                            <option value="{{ $group->id }}" {{ $selectedStudyGroup == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>#</th><th>Teks Soal</th><th>Mata Pelajaran</th><th>Rombel</th><th>Tahun Ajaran</th><th>Tingkat</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($questions as $question)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $question->question_text }}</td>
                                <td>{{ $question->subject->name ?? '' }}</td>
                                <td>{{ $question->studyGroup->name ?? '' }}</td>
                                <td>{{ $question->academicYear->name ?? '' }}</td>
                                <td>{{ ucfirst($question->difficulty) }}</td>
                                <td>
                                    <a href="{{ route('quiz-questions.show', ['id' => $question->id, 'school_id' => $question->school_id]) }}" class="btn btn-sm btn-info">Lihat</a>
                                    <a href="{{ route('quiz-questions.edit', ['id' => $question->id, 'school_id' => $question->school_id]) }}" class="btn btn-sm btn-warning">Ubah</a>
                                    <form action="{{ route('quiz-questions.destroy', ['id' => $question->id, 'school_id' => $question->school_id]) }}" method="post" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" onclick="return confirm('Hapus soal ini?')">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">Tidak ada soal ditemukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
