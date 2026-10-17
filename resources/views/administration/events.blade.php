@extends('layouts.app')

@section('title', 'Kalender Akademik')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Kalender Akademik</h1>
        <a href="{{ route('dashboard') }}" class="rounded border border-gray-400 px-3 py-1.5 text-sm hover:bg-gray-50">Kembali</a>
    </div>

    <form method="GET" action="{{ route('academic-events.index') }}" class="mb-6 bg-white rounded shadow p-4 text-sm flex gap-3 items-end">
        <label class="flex flex-col gap-1">
            Dari
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded border border-gray-300 px-2 py-1.5">
        </label>
        <label class="flex flex-col gap-1">
            Sampai
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded border border-gray-300 px-2 py-1.5">
        </label>
        <button class="rounded bg-gray-800 text-white px-3 py-1.5">Tampilkan</button>
    </form>

    @can('create', App\Models\AcademicEvent::class)
        <div class="bg-white rounded shadow p-6 mb-6 text-sm">
            <h2 class="font-semibold mb-3">Tambah Kegiatan</h2>

            <form method="POST" action="{{ route('academic-events.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input type="text" name="title" required maxlength="200" placeholder="Judul kegiatan"
                           value="{{ old('title') }}" class="rounded border border-gray-300 px-2 py-1.5">
                    <select name="category" class="rounded border border-gray-300 px-2 py-1.5">
                        @foreach (App\Enums\AcademicEventCategory::cases() as $category)
                            <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    <label class="flex flex-col gap-1">
                        Mulai
                        <input type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"
                               class="rounded border border-gray-300 px-2 py-1.5">
                    </label>
                    <label class="flex flex-col gap-1">
                        Selesai (opsional)
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"
                               class="rounded border border-gray-300 px-2 py-1.5">
                    </label>
                    <select name="academic_year_id" class="rounded border border-gray-300 px-2 py-1.5">
                        <option value="">-- lintas tahun ajaran --</option>
                        @foreach ($academicYears as $item)
                            <option value="{{ $item->id }}" @selected(old('academic_year_id') == $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <textarea name="description" rows="2" placeholder="Deskripsi (opsional)"
                          class="w-full rounded border border-gray-300 px-2 py-1.5">{{ old('description') }}</textarea>
                <button class="rounded bg-indigo-600 text-white px-3 py-1.5">Simpan Kegiatan</button>
            </form>
        </div>
    @endcan

    <div class="bg-white rounded shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-2">Waktu</th>
                    <th class="px-4 py-2">Kegiatan</th>
                    <th class="px-4 py-2">Kategori</th>
                    <th class="px-4 py-2">Tahun Ajaran</th>
                    @can('create', App\Models\AcademicEvent::class)
                        <th class="px-4 py-2">Aksi</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ $event->starts_at->format('d M Y H:i') }}
                            @if ($event->ends_at)
                                <span class="text-gray-500">– {{ $event->ends_at->format('d M Y H:i') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="font-medium">{{ $event->title }}</div>
                            @if ($event->description)
                                <div class="text-gray-500 text-xs">{{ $event->description }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $event->category }}</td>
                        <td class="px-4 py-2">{{ $event->academicYear->name ?? '—' }}</td>
                        @can('update', $event)
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('academic-events.destroy', $event) }}"
                                      onsubmit="return confirm('Hapus kegiatan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr class="border-t border-gray-100">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada kegiatan pada rentang ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
