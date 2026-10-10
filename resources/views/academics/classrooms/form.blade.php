<form method="POST" action="{{ $action }}" class="bg-white rounded shadow p-6 max-w-2xl space-y-4">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="name">Nama Ruang</label>
            <input type="text" name="name" id="name" required maxlength="100"
                   value="{{ old('name', $classroom?->name) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="building">Gedung</label>
            <input type="text" name="building" id="building" maxlength="100"
                   value="{{ old('building', $classroom?->building) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @error('building') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="capacity">Kapasitas</label>
            <input type="number" name="capacity" id="capacity" min="1" max="500"
                   value="{{ old('capacity', $classroom?->capacity) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @error('capacity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="status">Status</label>
            <select name="status" id="status" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                @foreach (App\Enums\ClassroomStatus::cases() as $status)
                    <option value="{{ $status->value }}"
                        @selected(old('status', $classroom?->status?->value ?? 'active') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <button class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">Simpan</button>
</form>