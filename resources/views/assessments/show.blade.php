@extends('layouts.app')

@section('title', $assessment->title)

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $assessment->title }}</h1>
            <p class="text-sm text-gray-600">
                {{ $assessment->teachingAssignment?->subject?->name }}
                — {{ $assessment->teachingAssignment?->studyGroup?->name }}
                — {{ $assessment->assessment_date->format('d M Y') }}
                — Maks {{ rtrim(rtrim(number_format((float) $assessment->max_score, 2), '0'), '.') }}
                @if ($assessment->category)
                    — {{ $assessment->category->name }}
                @endif
            </p>
        </div>
        <div class="flex gap-2 text-sm">
            @if ($assessment->status === \App\Enums\AssessmentStatus::Published)
                <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Terbit</span>
            @else
                <span class="rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 text-xs">Draft</span>
            @endif
            <a href="{{ route('assessments.index') }}" class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Kembali</a>
        </div>
    </div>

    @if ($assessment->status === \App\Enums\AssessmentStatus::Draft)
        @can('publish', $assessment)
            <div class="bg-white rounded shadow p-4 mb-6 text-sm flex items-center justify-between">
                <p class="text-gray-600">
                    Menerbitkan asesmen akan memfinalisasi semua nilai yang terisi.
                    Setelah terbit, perubahan hanya melalui koreksi yang tercatat.
                </p>
                <form method="POST" action="{{ route('assessments.publish', $assessment) }}">
                    @csrf
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Terbitkan</button>
                </form>
            </div>
        @endcan
    @endif

    @can('update', $assessment)
        <div class="bg-white rounded shadow p-6 mb-6">
            <h2 class="font-semibold mb-3">Isi Nilai</h2>

            <form method="POST" action="{{ route('assessments.scores.save', $assessment) }}" class="text-sm">
                @csrf
                <table class="min-w-full">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-2">Siswa</th>
                            <th class="px-4 py-2 w-32">Nilai</th>
                            <th class="px-4 py-2">Umpan Balik</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-t">
                                <td class="px-4 py-2">{{ $row['student']?->full_name ?? ('#'.$row['student_id']) }}</td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}"
                                           name="scores[{{ $row['student_id'] }}][score]"
                                           value="{{ old('scores.'.$row['student_id'].'.score', $row['score']) }}"
                                           class="rounded border border-gray-300 px-2 py-1 w-full">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" maxlength="2000"
                                           name="scores[{{ $row['student_id'] }}][feedback]"
                                           value="{{ old('scores.'.$row['student_id'].'.feedback', $row['feedback']) }}"
                                           class="rounded border border-gray-300 px-2 py-1 w-full">
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t">
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">
                                    Tidak ada siswa pada rombel penugasan ini pada tanggal asesmen.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($rows->isNotEmpty())
                    <div class="mt-4">
                        <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Nilai</button>
                    </div>
                @endif
            </form>
        </div>
    @endcan

    @if ($assessment->scores->isNotEmpty())
        <div class="bg-white rounded shadow overflow-x-auto">
            <h2 class="font-semibold px-4 pt-4">Nilai Tersimpan</h2>
            <table class="min-w-full text-sm mt-2">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">Siswa</th>
                        <th class="px-4 py-2">Nilai</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Riwayat Koreksi</th>
                        @can('correct', $assessment)
                            <th class="px-4 py-2">Koreksi</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assessment->scores->sortBy(fn ($score) => $score->student?->full_name ?? '') as $score)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $score->student?->full_name }}</td>
                            <td class="px-4 py-2">{{ $score->score ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($score->finalized_at)
                                    <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Difinalisasi</span>
                                @else
                                    <span class="rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 text-xs">Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-600">
                                @if ($score->wasCorrected())
                                    {{ $score->correction_count }}× — nilai sebelumnya {{ $score->previous_score ?? '—' }}
                                    ({{ $score->corrected_at?->format('d M Y H:i') }})
                                @else
                                    —
                                @endif
                            </td>
                            @can('correct', $assessment)
                                <td class="px-4 py-2">
                                    <form method="POST" action="{{ route('assessment-scores.correct', $score) }}"
                                          class="flex gap-1 items-center">
                                        @csrf
                                        @method('PUT')
                                        <input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}"
                                               name="score" value="{{ $score->score }}" required
                                               class="rounded border border-gray-300 px-2 py-1 w-24">
                                        <input type="text" name="reason" maxlength="255" placeholder="Alasan" required
                                               class="rounded border border-gray-300 px-2 py-1">
                                        <button class="rounded bg-gray-800 text-white px-2 py-1">Koreksi</button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection