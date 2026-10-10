<form method="POST" action="{{ $action }}" class="bg-white rounded shadow p-6 max-w-2xl space-y-4">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label class="block text-sm font-medium mb-1" for="name">Nama Tahun Ajaran</label>
        <input type="text" name="name" id="name" required maxlength="50" value="{{ old('name', $academicYear?->name) }}"
               placeholder="2026/2027" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="starts_on">Mulai</label>
            <input type="date" name="starts_on" id="starts_on" required
                   value="{{ old('starts_on', $academicYear?->starts_on?->format('Y-m-d')) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @error('starts_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="ends_on">Selesai</label>
            <input type="date" name="ends_on" id="ends_on" required
                   value="{{ old('ends_on', $academicYear?->ends_on?->format('Y-m-d')) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @error('ends_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $academicYear?->is_active ?? false))>
        Jadikan tahun ajaran aktif (otomatis menonaktifkan tahun ajaran aktif sebelumnya)
    </label>

    <button class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">Simpan</button>
</form>