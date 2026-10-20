@extends('layouts.app')
@section('title', 'Buat Kuis')
@section('content')
<div class="container-fluid">
    <h1>Buat Kuis Baru</h1>
    @if ($errors->any())
        <div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('quizzes.store') }}">
        @csrf
        <div class="form-group mb-3">
            <label>Judul Kuis</label>
            <input type="text" name="title" class="form-control" required value="{{ old('title') }}">
        </div>
        <div class="form-group mb-3">
            <label>Deskripsi</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
        </div>
        <div class="form-group mb-3">
            <label>Tahun Ajaran</label>
            <select name="academic_year_id" class="form-control" required>
                <option value="">Pilih Tahun Ajaran</option>
                @foreach($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-3">
            <label>Mata Pelajaran</label>
            <select name="subject_id" class="form-control" required>
                <option value="">Pilih Mata Pelajaran</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-3">
            <label>Rombel</label>
            <select name="study_group_id" class="form-control" required>
                <option value="">Pilih Rombel</option>
                @foreach($studyGroups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group mb-3">
            <label>Waktu (menit)</label>
            <input type="number" name="time_limit" class="form-control" placeholder="Opsional" value="{{ old('time_limit') }}">
        </div>
        <div class="form-group mb-3">
            <label>Skor Lulus (%)</label>
            <input type="number" name="passing_score" class="form-control" min="0" max="100" value="{{ old('passing_score', 70) }}">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="shuffle_questions" value="1" {{ old('shuffle_questions') ? 'checked' : '' }}>
            <label>Acak urutan soal</label>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="shuffle_choices" value="1" {{ old('shuffle_choices') ? 'checked' : '' }}>
            <label>Acak pilihan jawaban</label>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Kuis</button>
        <a href="{{ route('quizzes.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection
