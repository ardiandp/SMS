@extends('layouts.app')

@section('title', 'Nilai Akhir')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Nilai Akhir</h1>
        <a href="{{ route('assessments.index') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <form method="GET" action="{{ route('final-grades.index') }}" class="mb-4 flex gap-2 text-sm">
        <select name="teaching_assignment_id" required class="rounded border border-gray-300 px-2 py-1.5">
            <option value="">-- pilih penugasan mengajar --</option>
            @foreach ($assignments as $item)
                <option value="{{ $item->id }}" @selected(request('teaching_assignment_id') == $item->id)>
                    {{ $item->subject?->name }} — {{ $item->studyGroup?->name }}
                </option>
            @endforeach
        </select>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Tampilkan</button>
    </form>

    @if ($assignment !== null)
        @can('calculate', App\Models\FinalGrade::class)
            <div class="bg-white rounded shadow p-4 mb-6 text-sm flex items-center justify-between">
                <p class="text-gray-600">
                    @if ($activePolicy)
                        Menghitung menggunakan kebijakan
                        <strong>{{ $activePolicy->name }} v{{ $activePolicy->version }}</strong>.
                        Nilai yang sudah difinalisasi tidak akan dihitung ulang.
                    @else
                        Belum ada kebijakan penilaian aktif untuk tahun ajaran penugasan ini.
                        Aktifkan satu di halaman Kebijakan terlebih dahulu.
                    @endif
                </p>
                @if ($activePolicy)
                    <form method="POST" action="{{ route('final-grades.calculate', $assignment) }}">
                        @csrf
                        <input type="hidden" name="grading_policy_id" value="{{ $activePolicy->id }}">
                        <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Hitung Nilai Akhir</button>
                    </form>
                @endif
            </div>
        @endcan

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">Siswa</th>
                        <th class="px-4 py-2">Nilai</th>
                        <th class="px-4 py-2">Predikat</th>
                        <th class="px-4 py-2">Versi Kalkulasi</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grades as $grade)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $grade->student?->full_name }}</td>
                            <td class="px-4 py-2">{{ $grade->numeric_score ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $grade->grade_label ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $grade->calculation_version ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($grade->status === \App\Enums\FinalGradeStatus::Finalized)
                                    <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Difinalisasi</span>
                                @else
                                    <span class="rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 text-xs">Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex gap-2 justify-end items-center">
                                    @if ($grade->status === \App\Enums\FinalGradeStatus::Draft && $grade->numeric_score !== null)
                                        @can('finalize', $grade)
                                            <form method="POST" action="{{ route('final-grades.finalize', $grade) }}">
                                                @csrf
                                                <button class="text-indigo-600 hover:underline">Finalisasi</button>
                                            </form>
                                        @endcan
                                    @endif
                                    @can('correct', $grade)
                                        <form method="POST" action="{{ route('final-grades.correct', $grade) }}"
                                              class="flex gap-1 items-center">
                                            @csrf
                                            @method('PUT')
                                            <input type="number" step="0.01" min="0" max="100" name="numeric_score"
                                                   value="{{ $grade->numeric_score }}" required
                                                   class="rounded border border-gray-300 px-2 py-1 w-20">
                                            <input type="text" name="grade_label" maxlength="5" placeholder="Predikat"
                                                   value="{{ $grade->grade_label }}"
                                                   class="rounded border border-gray-300 px-2 py-1 w-20">
                                            <input type="text" name="reason" maxlength="255" placeholder="Alasan" required
                                                   class="rounded border border-gray-300 px-2 py-1">
                                            <button class="rounded bg-gray-800 text-white px-2 py-1">Koreksi</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                Belum ada nilai akhir. Klik "Hitung Nilai Akhir" untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection