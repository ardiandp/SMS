@extends('layouts.app')

@section('title', 'Penilaian')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Asesmen & Nilai</h1>
        <div class="flex gap-2 text-sm">
            @can('viewAny', App\Models\AssessmentCategory::class)
                <a href="{{ route('assessments.categories.index') }}"
                   class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Kategori</a>
            @endcan
            @can('viewAny', App\Models\GradingPolicy::class)
                <a href="{{ route('grading-policies.index') }}"
                   class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Kebijakan</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('assessments.index') }}" class="mb-4 flex gap-2 text-sm">
        <select name="status" class="rounded border border-gray-300 px-2 py-1.5">
            <option value="">Semua status</option>
            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            <option value="published" @selected(request('status') === 'published')>Terbit</option>
        </select>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Filter</button>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Judul</th>
                    <th class="px-4 py-2">Mapel / Rombel</th>
                    <th class="px-4 py-2">Kategori</th>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">Skor Maks</th>
                    <th class="px-4 py-2">Nilai Terisi</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assessments as $assessment)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $assessment->title }}</td>
                        <td class="px-4 py-2">
                            {{ $assessment->teachingAssignment?->subject?->name }}
                            — {{ $assessment->teachingAssignment?->studyGroup?->name }}
                        </td>
                        <td class="px-4 py-2">{{ $assessment->category?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $assessment->assessment_date->format('d M Y') }}</td>
                        <td class="px-4 py-2">{{ rtrim(rtrim(number_format((float) $assessment->max_score, 2), '0'), '.') }}</td>
                        <td class="px-4 py-2">{{ $assessment->scores_count }}</td>
                        <td class="px-4 py-2">
                            @if ($assessment->status === \App\Enums\AssessmentStatus::Published)
                                <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Terbit</span>
                            @else
                                <span class="rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 text-xs">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('assessments.show', $assessment) }}"
                               class="text-indigo-600 hover:underline">Buka</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Belum ada asesmen.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assessments->links() }}</div>
@endsection