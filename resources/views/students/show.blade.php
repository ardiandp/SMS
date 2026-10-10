@extends('layouts.app')

@section('title', $student->full_name)

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $student->full_name }}</h1>
        <div class="flex gap-2">
            @can('update', $student)
                <a href="{{ route('students.edit', $student) }}" class="rounded border border-indigo-600 text-indigo-600 px-3 py-1.5 text-sm hover:bg-indigo-50">Ubah</a>
            @endcan
            @can('delete', $student)
                <form method="POST" action="{{ route('students.destroy', $student) }}"
                      onsubmit="return confirm('Nonaktifkan siswa ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="rounded border border-red-600 text-red-600 px-3 py-1.5 text-sm hover:bg-red-50">Nonaktifkan</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="bg-white rounded shadow p-6">
            <h2 class="font-medium mb-2">Profil</h2>
            <dl class="text-sm space-y-1">
                <div><dt class="inline text-gray-500">NIS: </dt><dd class="inline">{{ $student->nis }}</dd></div>
                <div><dt class="inline text-gray-500">NISN: </dt><dd class="inline">{{ $student->nisn ?: '—' }}</dd></div>
                <div><dt class="inline text-gray-500">Lahir: </dt><dd class="inline">{{ $student->birth_date?->format('d-m-Y') ?: '—' }}</dd></div>
                <div><dt class="inline text-gray-500">Gender: </dt><dd class="inline">{{ $student->gender?->label() ?: '—' }}</dd></div>
                <div><dt class="inline text-gray-500">Status: </dt><dd class="inline">{{ $student->status->label() }}</dd></div>
            </dl>

            <h2 class="font-medium mt-4 mb-2">Riwayat Status</h2>
            <ul class="text-sm space-y-1">
                @forelse ($student->statusHistories as $history)
                    <li>
                        {{ $history->changed_at?->format('d-m-Y H:i') }} —
                        {{ $history->from_status ?: 'mulai' }} → {{ $history->to_status }}
                        @if ($history->reason) <span class="text-gray-500">({{ $history->reason }})</span> @endif
                    </li>
                @empty
                    <li class="text-gray-500">Belum ada riwayat.</li>
                @endforelse
            </ul>
        </div>

        <div class="bg-white rounded shadow p-6">
            <h2 class="font-medium mb-2">Wali</h2>
            <ul class="text-sm space-y-1 mb-4">
                @forelse ($student->guardians as $guardian)
                    <li>{{ $guardian->full_name }} ({{ $guardian->pivot->relationship }})</li>
                @empty
                    <li class="text-gray-500">Belum ada wali tertaut.</li>
                @endforelse
            </ul>

            @can('update', $student)
                <form method="POST" action="{{ route('students.guardians.link', $student) }}" class="flex gap-2 items-end">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">ID Wali</label>
                        <input name="guardian_id" required class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">Hubungan</label>
                        <input name="relationship" value="parent" required class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                    </div>
                    <button class="rounded bg-gray-800 text-white px-3 py-1.5 text-sm">Tautkan</button>
                </form>
            @endcan

            <h2 class="font-medium mt-6 mb-2">Dokumen</h2>
            <ul class="text-sm space-y-1 mb-4">
                @forelse ($student->documents as $document)
                    <li>
                        {{ $document->document_type }} —
                        <a href="{{ route('documents.download', $document) }}" class="text-indigo-600 hover:underline">unduh</a>
                    </li>
                @empty
                    <li class="text-gray-500">Belum ada dokumen.</li>
                @endforelse
            </ul>

            @can('upload', App\Models\StudentDocument::class)
                <form method="POST" action="{{ route('students.documents.store', $student) }}" enctype="multipart/form-data"
                      class="space-y-2">
                    @csrf
                    <input name="document_type" placeholder="Jenis (akte, kk, ...)" required
                           class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm">
                    <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf"
                           class="w-full text-sm">
                    <button class="rounded bg-indigo-600 text-white px-3 py-1.5 text-sm hover:bg-indigo-700">Unggah</button>
                </form>
            @endcan
        </div>
    </div>
@endsection