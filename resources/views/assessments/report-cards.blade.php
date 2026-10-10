@extends('layouts.app')

@section('title', 'Rapor')

@section('content')
    <h1 class="text-xl font-semibold mb-4">Rapor</h1>

    <form method="GET" action="{{ route('report-cards.index') }}" class="mb-6 bg-white rounded shadow p-4 text-sm grid grid-cols-1 md:grid-cols-4 gap-3">
        <select name="student_id" required class="rounded border border-gray-300 px-2 py-1.5">
            <option value="">-- pilih siswa --</option>
            @foreach ($students as $item)
                <option value="{{ $item->id }}" @selected(request('student_id') == $item->id)>{{ $item->full_name }}</option>
            @endforeach
        </select>
        <select name="academic_year_id" required class="rounded border border-gray-300 px-2 py-1.5">
            <option value="">-- tahun ajaran --</option>
            @foreach ($academicYears as $item)
                <option value="{{ $item->id }}" @selected(request('academic_year_id') == $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="semester_id" required class="rounded border border-gray-300 px-2 py-1.5">
            <option value="">-- semester --</option>
            @foreach ($semesters as $item)
                <option value="{{ $item->id }}" @selected(request('semester_id') == $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Tampilkan</button>
    </form>

    @if ($student !== null && $year !== null && $semester !== null)
        @can('publish', App\Models\ReportCard::class)
            <div class="bg-white rounded shadow p-4 mb-6 text-sm flex items-center justify-between">
                <p class="text-gray-600">
                    Menerbitkan rapor memindahkan seluruh nilai akhir yang sudah difinalisasi menjadi snapshot.
                    Nilai yang terbit tidak berubah walau perhitungan dihitung ulang; revisi membuat terbitan baru.
                </p>
                <form method="POST" action="{{ route('report-cards.publish') }}">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <input type="hidden" name="academic_year_id" value="{{ $year->id }}">
                    <input type="hidden" name="semester_id" value="{{ $semester->id }}">
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">
                        {{ $latest ? 'Terbitkan Revisi Baru' : 'Terbitkan Rapor' }}
                    </button>
                </form>
            </div>
        @endcan

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">Revisi</th>
                        <th class="px-4 py-2">Diterbitkan</th>
                        <th class="px-4 py-2">Oleh</th>
                        <th class="px-4 py-2">Jumlah Mapel</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($revisions as $card)
                        <tr class="border-t @if ($card->revision_no === $latest?->revision_no) bg-green-50 @endif">
                            <td class="px-4 py-2">Ke-{{ $card->revision_no }}</td>
                            <td class="px-4 py-2">{{ $card->published_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $card->publisher?->name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $card->items_count ?? $card->items()->count() }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('report-cards.show', $card) }}" class="text-indigo-600 hover:underline">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada rapor terbit untuk kombinasi ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection