@extends('layouts.app')
@section('title', 'Detail Submission')
@section('content')
<div class=\"container-fluid\">
    <h1>Detail Submission Kuis</h1>
    <div class=\"card mt-3\">
        <div class=\"card-body\">
            <p><strong>Kuis:</strong> {{ $submission->quiz->title ?? '-' }}</p>
            <p><strong>Siswa:</strong> {{ $submission->student->full_name ?? '-' }}</p>
            <p><strong>Sekolah:</strong> {{ $submission->school->name ?? '-' }}</p>
            <p><strong>Status:</strong>
                @switch($submission->status)
                    @case('draft') <span class=\"badge bg-secondary\">Draft</span> @break
                    @case('submitted') <span class=\"badge bg-warning text-dark\">Dikumpulkan</span> @break
                    @case('graded') <span class=\"badge bg-success\">Dinilai</span> @break
                    @default <span class=\"badge bg-info\">{{ $submission->status }}</span> @break
                @endswitch
            </p>
            <p><strong>Skor:</strong> {{ $submission->score ?? '-' }} / {{ $submission->max_score ?? '-' }}</p>
            <p><strong>Dikumpulkan pada:</strong> {{ $submission->submitted_at ?? '-' }}</p>
            <p><strong>Dinilai pada:</strong> {{ $submission->graded_at ?? '-' }}</p>
            <p><strong>Penilai:</strong> {{ $submission->grader->name ?? '-' }}</p>
            <p><strong>Feedback:</strong> {{ $submission->feedback ?? '-' }}</p>
            <hr>
            <h5>Jawaban</h5>
            @if($submission->answers && is_array($submission->answers))
                <ul class=\"list-group\">
                    @foreach($submission->answers as $questionId => $answer)
                        <li class=\"list-group-item\">{{ $questionId }}: {{ $answer }}</li>
                    @endforeach
                </ul>
            @else
                <p class=\"text-muted\">Belum ada jawaban.</p>
            @endif
        </div>
    </div>
    @if($submission->status === 'draft')
        <form action=\"{{ route('quiz-submissions.submit', ['id' => $submission->id]) }}\" method=\"post\" class=\"d-inline\">
            @csrf
            <button class=\"btn btn-primary\" onclick=\"return confirm('Kumpulkan submission ini?')\">Kumpulkan</button>
        </form>
    @endif
    <a href=\"{{ route('quiz-submissions.index') }}\" class=\"btn btn-secondary\">Kembali</a>
</div>
@endsection