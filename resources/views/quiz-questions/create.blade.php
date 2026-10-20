@extends('layouts.app')
@section('title', 'Tambah Soal')
@section('content')
<div class="container-fluid">
    <h1>Tambah Soal LMS</h1>
    @if ($errors->any())
        <div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('quiz-questions.store') }}">
        @csrf
        <div class="form-group">
            <label>Tahun Ajaran</label>
            <select name="academic_year_id" class="form-control" required>
                <option value="">Pilih Tahun Ajaran</option>
                @foreach($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Mata Pelajaran</label>
            <select name="subject_id" class="form-control" required>
                <option value="">Pilih Mata Pelajaran</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Rombel</label>
            <select name="study_group_id" class="form-control" required>
                <option value="">Pilih Rombel</option>
                @foreach($studyGroups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Teks Soal</label>
            <textarea name="question_text" class="form-control" rows="4" required></textarea>
        </div>
        <div class="form-group">
            <label>Pilihan Jawaban (pisah dengan koma)</label>
            <input type="text" name="choices" class="form-control" placeholder="A: pilihan 1, B: pilihan 2, C: pilihan 3" required>
            <small class="text-muted">Format: A: teks pilihan 1, B: teks pilihan 2</small>
        </div>
        <div class="form-group">
            <label>Index Jawaban Benar</label>
            <select name="correct_choice_index" class="form-control" required>
                @for($i = 0; $i < 4; $i++)
                    <option value="{{ $i }}">{{ strtoupper($i) }}</option>
                @endfor
            </select>
        </div>
        <div class="form-group">
            <label>Tingkat Kesulitan</label>
            <select name="difficulty" class="form-control">
                <option value="easy">Easy</option>
                <option value="medium" selected>Medium</option>
                <option value="hard">Hard</option>
            </select>
        </div>
        <div class="form-group">
            <label>Tipe Soal</label>
            <select name="question_type" class="form-control">
                <option value="multiple_choice" selected>Multiple Choice</option>
                <option value="true_false">True/False</option>
            </select>
        </div>
        <div class="form-group">
            <label>Dibuat Oleh</label>
            <select name="created_by" class="form-control">
                @foreach($users as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('quiz-questions.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection
