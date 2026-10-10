@extends('layouts.app')

@section('title', 'Rapor '.$card->student?->full_name)

@section('content')
    <div class="flex items-center justify-between mb-4 print:hidden">
        <h1 class="text-xl font-semibold">Rapor</h1>
        <div class="flex gap-2 text-sm">
            <button onclick="window.print()" class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Cetak</button>
            <a href="{{ route('report-cards.index', [
                'student_id' => $card->student_id,
                'academic_year_id' => $card->academic_year_id,
                'semester_id' => $card->semester_id,
            ]) }}" class="rounded border border-gray-400 px-3 py-1.5 hover:bg-gray-50">Kembali</a>
        </div>
    </div>

    <div class="bg-white rounded shadow p-8 print:shadow-none print:rounded-none">
        <div class="text-center border-b pb-4 mb-6">
            <h2 class="text-lg font-semibold">IRNIS School</h2>
            <p class="text-sm text-gray-600">Laporan Hasil Belajar — {{ $card->semester?->name }} ({{ $card->academicYear?->name }})</p>
            <p class="text-xs text-gray-500 mt-1">
                Terbit ke-{{ $card->revision_no }} pada {{ $card->published_at?->format('d M Y H:i') }}
                @if ($previous)
                    — menggantikan revisi ke-{{ $previous->revision_no }}
                @endif
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm mb-6">
            <div>
                <p class="text-gray-500">Nama Siswa</p>
                <p class="font-medium">{{ $card->student?->full_name }}</p>
            </div>
            <div>
                <p class="text-gray-500">NIS</p>
                <p class="font-medium">{{ $card->student?->nis ?? '—' }}</p>
            </div>
        </div>

        <table class="min-w-full text-sm border">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2 border">Mata Pelajaran</th>
                    <th class="px-4 py-2 border">Guru</th>
                    <th class="px-4 py-2 border">Nilai</th>
                    <th class="px-4 py-2 border">Predikat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($card->items as $item)
                    <tr class="border-t">
                        <td class="px-4 py-2 border">{{ $item->subject?->name ?? '—' }}</td>
                        <td class="px-4 py-2 border">{{ $item->teacher?->full_name ?? '—' }}</td>
                        <td class="px-4 py-2 border">{{ $item->final_grade_snapshot_json['numeric_score'] ?? '—' }}</td>
                        <td class="px-4 py-2 border">{{ $item->final_grade_snapshot_json['grade_label'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="text-xs text-gray-500 mt-4">
            Snapshot nilai pada saat penerbitan. Perubahan nilai setelah penerbitan tidak mengubah dokumen ini;
            buat revisi bila memang perlu dikoreksi.
        </p>
    </div>
@endsection