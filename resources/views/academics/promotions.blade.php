@extends('layouts.app')

@section('title', 'Kenaikan Kelas & Kelulusan')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Kenaikan Kelas &amp; Kelulusan</h1>
        <a href="{{ route('dashboard') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <form method="GET" action="{{ route('promotions.index') }}" class="mb-6 bg-white rounded shadow p-4 text-sm flex gap-3 items-end">
        <label class="flex flex-col gap-1">
            Tahun Ajaran
            <select name="academic_year_id" required class="rounded border border-gray-300 px-2 py-1.5">
                <option value="">-- pilih --</option>
                @foreach ($academicYears as $item)
                    <option value="{{ $item->id }}" @selected(request('academic_year_id') == $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Tampilkan</button>
    </form>

    @if ($year !== null)
        @can('create', App\Models\PromotionDecision::class)
            <div class="bg-white rounded shadow p-6 mb-6 text-sm">
                <h2 class="font-semibold mb-3">Catat Keputusan</h2>

                <form method="POST" action="{{ route('promotions.decide') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <select name="student_id" required class="rounded border border-gray-300 px-2 py-1.5">
                            <option value="">-- pilih siswa --</option>
                            @foreach ($students as $item)
                                <option value="{{ $item->id }}" @selected(old('student_id') == $item->id)>{{ $item->full_name }}</option>
                            @endforeach
                        </select>
                        <select name="decision" required class="rounded border border-gray-300 px-2 py-1.5">
                            <option value="">-- keputusan --</option>
                            @foreach (App\Enums\PromotionOutcome::cases() as $outcome)
                                <option value="{{ $outcome->value }}" @selected(old('decision') === $outcome->value)>{{ $outcome->label() }}</option>
                            @endforeach
                        </select>
                        <select name="from_grade_level_id" class="rounded border border-gray-300 px-2 py-1.5">
                            <option value="">-- kelas asal --</option>
                            @foreach ($gradeLevels as $item)
                                <option value="{{ $item->id }}" @selected(old('from_grade_level_id') == $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        <select name="to_grade_level_id" class="rounded border border-gray-300 px-2 py-1.5">
                            <option value="">-- kelas tujuan --</option>
                            @foreach ($gradeLevels as $item)
                                <option value="{{ $item->id }}" @selected(old('to_grade_level_id') == $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <textarea name="notes" rows="2" maxlength="1000" placeholder="Catatan (opsional)"
                              class="w-full rounded border border-gray-300 px-2 py-1.5">{{ old('notes') }}</textarea>
                    <input type="hidden" name="academic_year_id" value="{{ $year->id }}">
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Keputusan</button>
                </form>

                <p class="mt-2 text-xs text-gray-500">
                    Keputusan naik/tinggal kelas memerlukan kelas tujuan; keputusan lulus tidak.
                    Keputusan yang sama untuk siswa dan tahun ajaran yang sama akan diperbarui,
                    nilai sebelumnya tersimpan di log audit.
                </p>
            </div>
        @endcan

        <div class="bg-white rounded shadow overflow-x-auto mb-6">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">Siswa</th>
                        <th class="px-4 py-2">Kelas Asal</th>
                        <th class="px-4 py-2">Kelas Tujuan</th>
                        <th class="px-4 py-2">Keputusan</th>
                        <th class="px-4 py-2">Oleh</th>
                        <th class="px-4 py-2">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($decisions as $decision)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $decision->student?->full_name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $decision->fromGradeLevel?->name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $decision->toGradeLevel?->name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-indigo-100 text-indigo-800 px-2 py-0.5 text-xs">{{ $decision->decision->label() }}</span>
                            </td>
                            <td class="px-4 py-2">{{ $decision->decider?->name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $decision->decided_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada keputusan untuk tahun ajaran ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('issue', App\Models\GraduationRecord::class)
            <div class="bg-white rounded shadow p-6 mb-6 text-sm">
                <h2 class="font-semibold mb-3">Terbitkan Kelulusan</h2>

                <form method="POST" action="{{ route('promotions.graduations.issue') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <select name="student_id" required class="rounded border border-gray-300 px-2 py-1.5">
                            <option value="">-- pilih siswa --</option>
                            @foreach ($students as $item)
                                <option value="{{ $item->id }}" @selected(old('student_id') == $item->id)>{{ $item->full_name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="document_number" required maxlength="100" placeholder="Nomor dokumen kelulusan"
                               value="{{ old('document_number') }}" class="rounded border border-gray-300 px-2 py-1.5">
                    </div>
                    <input type="hidden" name="academic_year_id" value="{{ $year->id }}">
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Terbitkan</button>
                </form>

                <p class="mt-2 text-xs text-gray-500">
                    Penerbitan ulang untuk siswa dan tahun ajaran yang sama mengganti nomor dokumen;
                    nomor sebelumnya tersimpan di log audit.
                </p>
            </div>
        @endcan

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2">Siswa</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Nomor Dokumen</th>
                        <th class="px-4 py-2">Diterbitkan</th>
                        <th class="px-4 py-2">Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($graduations as $record)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $record->student?->full_name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($record->status === App\Enums\GraduationStatus::Issued)
                                    <span class="rounded bg-green-100 text-green-800 px-2 py-0.5 text-xs">Diterbitkan</span>
                                @else
                                    <span class="rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 text-xs">Menunggu</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $record->document_number ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $record->issued_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $record->issuer?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada catatan kelulusan untuk tahun ajaran ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
