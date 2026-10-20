@extends('layouts.app')
@section('title', 'Ubah Soal')
@section('content')
<div class="container-fluid">
    <h1>Ubah Soal</h1>
    @if ($errors->any())
        <div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ route('quiz-questions.update', ['id' => $question->id]) }}">
        @csrf @method('PUT')
        <div class="form-group">
            <label>Tahun Ajaran</label>
            <select name="academic_year_id" class="form-control" required>
                @foreach($academicYears as $year)
                    <option value="{{ $year->id }}" {{ $question->academicYear->id == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Mata Pelajaran</label>
            <select name="subject_id" class="form-control" required>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" {{ $question->subject->id == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Rombel</label>
            <select name="study_group_id" class="form-control" required>
                @foreach($studyGroups as $group)
                    <option value="{{ $group->id }}" {{ $question->studyGroup->id == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Teks Soal</label>
            <textarea name="question_text" class="form-control" rows="4" required>{{ $question->question_text }}</textarea>
        </div>
        <div class="form-group">
            <label>Pilihan Jawaban (pisah dengan koma)</label>
            <input type="text" name="choices" class="form-control" value="{{ implode(', ', array_values($question->choices)) }}" required>
        </div>
        <div class="form-group">
            <label>Index Jawaban Benar</label>
            <select name="correct_choice_index" class="form-control" required>
                @for($i = 0; $i < 4; $i++)
                    <option value="{{ $i }}" {{ $question->correct_choice_index == $i ? 'selected' : '' }}>{{ strtoupper($i) }}</option>
                @endfor
            </select>
        </div>
        <div class="form-group">
            <label>Tingkat Kesulitan</label>
            <select name="difficulty" class="form-control">
                <option value="easy" {{ $question->difficulty == 'easy' ? 'selected' : '' }}>Easy</option>
                <option value="medium" {{ $question->difficulty == 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="hard" {{ $question->difficulty == 'hard' ? 'selected' : '' }}>Hard</option>
            </select>
        </div>
        <div class="form-group">
            <label>Tipe Soal</label>
            <select name="question_type" class="form-control">
                <option value="multiple_choice" {{ $question->question_type == 'multiple_choice' ? 'selected' : '' }}>Multiple Choice</option>
                <option value="true_false" {{ $question->question_type == 'true_false' ? 'selected' : '' }}>True/False</option>
            </select>
        </div>
        <div class="form-group">
            <label>Diperbarui Oleh</label>
            <select name="updated_by" class="form-control">
                @foreach($users as $id => $name)
                    <option value="{{ $id }}" {{ $question->updated_by == $id ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Perbarui</button>
        <a href="{{ route('quiz-questions.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection
