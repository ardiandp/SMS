@php($employee = $employee ?? null)

<form method="POST"
      action="{{ $employee ? route('employees.update', $employee) : route('employees.store') }}"
      class="bg-white rounded shadow p-6 max-w-xl space-y-4">
    @csrf

    <div>
        <label class="block text-sm font-medium mb-1">Nomor Induk *</label>
        <input name="employee_number" value="{{ old('employee_number', $employee?->employee_number) }}" required
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Nama Lengkap *</label>
        <input name="full_name" value="{{ old('full_name', $employee?->full_name) }}" required
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Status Kepegawaian *</label>
        <select name="employment_status" required class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @foreach (App\Enums\EmploymentStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(old('employment_status', $employee?->employment_status?->value ?? 'permanent') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Tanggal Bergabung</label>
        <input type="date" name="joined_on" value="{{ old('joined_on', $employee?->joined_on?->format('Y-m-d')) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Tanggal Keluar</label>
        <input type="date" name="left_on" value="{{ old('left_on', $employee?->left_on?->format('Y-m-d')) }}"
               class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
    </div>

    <button class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">Simpan</button>
</form>