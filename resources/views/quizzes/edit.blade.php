@extends('layouts.app')
@section('title', 'Ubah Kuis')
@section('content')
<div class="container-fluid">
    <h1>Ubah Kuis</h1>
    @if ($errors->any())
        <div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('quizzes.update', ['id' => $quiz->id]) }}">
        @csrf @method('PUT')
        <div class="form-group mb-3">
            <label>Judul Kuis</label>
            <input type="text" name="title" class="form-control" required value="{{ old('title', $quiz->title) }}">
        </div>
        <div class="form-group mb-3">
            <label>Deskripsi</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $quiz->description ?? '') }}</textarea>
        </div>
        <div class="form-group mb-3">
            <label>Tahun Ajaran</label>
            <select name="academic_year_id" class="form-control" required>
                <option value="">Pilih Tahun Ajaran</option>
                @foreach($academicYears as $year)
                    <option value="{{ $year->id }}" {{ old('academic_year_id', $quiz->academic_year_id) == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-3">
            <label>Mata Pelajaran</label>
            <select name="subject_id" class="form-control" required>
                <option value="">Pilih Mata Pelajaran</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" {{ old('subject_id', $quiz->subject_id) == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-3">
            <label>Rombel</label>
            <select name="study_group_id" class="form-control" required>
                <option value="">Pilih Rombel</option>
                @foreach($studyGroups as $group)
                    <option value="{{ $group->id }}" {{ old('study_group_id', $quiz->study_group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-3">
            <label>Waktu (menit)</label>
            <input type="number" name="time_limit" class="form-control" placeholder="Opsional" value="{{ old('time_limit', $quiz->time_limit) }}">
        </div>
        <div class="form-group mb-3">
            <label>Skor Lulus (%)</label>
            <input type="number" name="passing_score" class="form-control" min="0" max="100" value="{{ old('passing_score', $quiz->passing_score) }}">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="shuffle_questions" value="1" {{ old('shuffle_questions', $quiz->shuffle_questions) ? 'checked' : '' }}>
            <label>Acak urutan soal</label>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="shuffle_choices" value="1" {{ old('shuffle_choices', $quiz->shuffle_choices) ? 'checked' : '' }}>
            <label>Acak pilihan jawaban</label>
        </div>
        <button type="submit" class="btn btn-primary">Perbarui Kuis</button>
        <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection
