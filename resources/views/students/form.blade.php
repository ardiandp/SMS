@php($student = $student ?? null)

<form method="POST" action="{{ $action }}" class="bg-white rounded shadow p-6 max-w-xl space-y-4">
    @csrf

    <div>
        <label class="block text-sm font-medium mb-1">NIS *</label>
        <input name="nis" value="{{ old('nis', $student?->nis) }}" required
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">NISN</label>
        <input name="nisn" value="{{ old('nisn', $student?->nisn) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Nama Lengkap *</label>
        <input name="full_name" value="{{ old('full_name', $student?->full_name) }}" required
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Tanggal Lahir</label>
        <input type="date" name="birth_date" value="{{ old('birth_date', $student?->birth_date?->format('Y-m-d')) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Jenis Kelamin</label>
        <select name="gender" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            <option value="">—</option>
            @foreach (App\Enums\Gender::cases() as $g)
                <option value="{{ $g->value }}" @selected(old('gender', $student?->gender?->value) === $g->value)>{{ $g->label() }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Status *</label>
        <select name="status" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @foreach (App\Enums\StudentStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(old('status', $student?->status?->value ?? 'active') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>

    <button class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">Simpan</button>
</form>