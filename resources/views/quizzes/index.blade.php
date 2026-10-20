@extends('layouts.app')
@section('title', 'Kuis LMS')
@section('content')
<div class="container-fluid">
    <h1>[[ __('LMS / Kuis') ]]</h1>
    @if (session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
    <div class="card">
        <div class="card-header">
            <div class="card-actions">
                <a href="{{ route('quizzes.create') }}" class="btn btn-primary">+ Buat Kuis</a>
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
                    <thead><tr><th>#</th><th>Judul</th><th>Deskripsi</th><th>Mata Pelajaran</th><th>Rombel</th><th>Tahun Ajaran</th><th>Aktif</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($quizzes as $quiz)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $quiz->title }}</td>
                                <td>{{ $quiz->description ?? '-' }}</td>
                                <td>{{ $quiz->subject->name ?? '' }}</td>
                                <td>{{ $quiz->studyGroup->name ?? '' }}</td>
                                <td>{{ $quiz->academicYear->name ?? '' }}</td>
                                <td>{{ $quiz->is_active ? 'Aktif' : 'Non-Aktif' }}</td>
                                <td>
                                    <a href="{{ route('quizzes.show', ['id' => $quiz->id]) }}" class="btn btn-sm btn-info">Lihat</a>
                                    <a href="{{ route('quizzes.edit', ['id' => $quiz->id]) }}" class="btn btn-sm btn-warning">Ubah</a>
                                    @if($quiz->is_active)
                                        <form action="{{ route('quizzes.destroy', ['id' => $quiz->id]) }}" method="post" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger" onclick="return confirm('Nonaktifkan kuis ini?')">Nonaktifkan</button>
                                        </form>
                                    @else
                                        <form action="{{ route('quizzes.restore', ['id' => $quiz->id]) }}" method="post" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success" onclick="return confirm('Aktifkan kuis ini?')">Aktifkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center">Tidak ada kuis ditemukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
