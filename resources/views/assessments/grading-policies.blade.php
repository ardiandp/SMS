@extends('layouts.app')

@section('title', 'Kebijakan Penilaian')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Kebijakan Penilaian</h1>
        <a href="{{ route('assessments.index') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    @can('create', App\Models\GradingPolicy::class)
        <div class="bg-white rounded shadow p-6 mb-6 text-sm">
            <h2 class="font-semibold mb-3">Buat Versi Kebijakan Baru</h2>

            <form method="POST" action="{{ route('grading-policies.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <input type="text" name="name" required maxlength="100" placeholder="Nama kebijakan" value="{{ old('name') }}"
                           class="rounded border border-gray-300 px-2 py-1.5">
                    <select name="academic_year_id" required class="rounded border border-gray-300 px-2 py-1.5">
                        <option value="">-- tahun ajaran --</option>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2">
                        Pembulatan (desimal)
                        <input type="number" name="config[rounding]" min="0" max="4" value="{{ old('config.rounding', 2) }}"
                               class="rounded border border-gray-300 px-2 py-1.5 w-20">
                    </label>
                </div>

                <div>
                    <p class="font-medium mb-1">Bobot per kategori (%) — total harus 100</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach ($categories as $category)
                            <label class="flex items-center justify-between gap-2 border rounded px-2 py-1.5">
                                <span>{{ $category->name }}</span>
                                <input type="number" step="0.01" min="0" max="100"
                                       name="config[weights][{{ $category->id }}]"
                                       value="{{ old('config.weights.'.$category->id, $category->default_weight) }}"
                                       class="rounded border border-gray-300 px-2 py-1 w-20">
                            </label>
                        @endforeach
                    </div>
                    @if ($categories->isEmpty())
                        <p class="text-xs text-gray-500 mt-1">Buat kategori asesmen terlebih dahulu.</p>
                    @endif
                </div>

                <div>
                    <p class="font-medium mb-1">Batas Nilai (urut menurun)</p>
                    <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                        @foreach (old('config.grade_bands', [['label' => 'A', 'min' => 90], ['label' => 'B', 'min' => 80], ['label' => 'C', 'min' => 70], ['label' => 'D', 'min' => 60]]) as $index => $band)
                            <div class="flex items-center gap-1">
                                <input type="text" name="config[grade_bands][{{ $index }}][label]" maxlength="10"
                                       value="{{ $band['label'] }}" placeholder="Label" required
                                       class="rounded border border-gray-300 px-2 py-1 w-16">
                                <input type="number" step="0.01" min="0" max="100"
                                       name="config[grade_bands][{{ $index }}][min]" value="{{ $band['min'] }}" required
                                       class="rounded border border-gray-300 px-2 py-1 w-20">
                            </div>
                        @endforeach
                    </div>
                </div>

                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Draft</button>
            </form>

            <p class="mt-2 text-xs text-gray-500">
                Versi kebijakan tidak dapat dihapus, hanya diaktifkan. Mengaktifkan versi baru mengarsipkan versi aktif sebelumnya.
            </p>
        </div>
    @endcan

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">Tahun Ajaran</th>
                    <th class="px-4 py-2">Versi</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($policies as $policy)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $policy->name }}</td>
                        <td class="px-4 py-2">{{ $policy->academicYear?->name }}</td>
                        <td class="px-4 py-2">v{{ $policy->version }}</td>
                        <td class="px-4 py-2">
                            @if ($policy->status === \App\Enums\GradingPolicyStatus::Active)
                                <span class="rounded bg-green-100 text-green-800 px-2 py-0.5 text-xs">Aktif</span>
                            @elseif ($policy->status === \App\Enums\GradingPolicyStatus::Archived)
                                <span class="rounded bg-gray-100 text-gray-700 px-2 py-0.5 text-xs">Diarsipkan</span>
                            @else
                                <span class="rounded bg-yellow-100 text-yellow-800 px-2 py-0.5 text-xs">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if ($policy->status === \App\Enums\GradingPolicyStatus::Draft)
                                @can('activate', $policy)
                                    <form method="POST" action="{{ route('grading-policies.activate', $policy) }}">
                                        @csrf
                                        <button class="text-indigo-600 hover:underline">Aktifkan</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="border-t">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada kebijakan penilaian.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $policies->links() }}</div>
@endsection