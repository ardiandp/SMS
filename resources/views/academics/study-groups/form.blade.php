<form method="POST" action="{{ $action }}" class="bg-white rounded shadow p-6 max-w-2xl space-y-4">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="academic_year_id">Tahun Ajaran</label>
            <select name="academic_year_id" id="academic_year_id" required
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                <option value="">-- pilih --</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}"
                        @selected(old('academic_year_id', $studyGroup?->academic_year_id) == $year->id)>
                        {{ $year->name }}
                    </option>
                @endforeach
            </select>
            @error('academic_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1" for="grade_level_id">Kelas</label>
            <select name="grade_level_id" id="grade_level_id" required
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                <option value="">-- pilih --</option>
                @foreach ($gradeLevels as $gradeLevel)
                    <option value="{{ $gradeLevel->id }}"
                        @selected(old('grade_level_id', $studyGroup?->grade_level_id) == $gradeLevel->id)>
                        {{ $gradeLevel->name }} ({{ $gradeLevel->education_level }})
                    </option>
                @endforeach
            </select>
            @error('grade_level_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="name">Nama Rombel</label>
            <input type="text" name="name" id="name" required maxlength="100"
                   value="{{ old('name', $studyGroup?->name) }}" placeholder="7A"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-gray-500">Unik per tahun ajaran.</p>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1" for="capacity">Kapasitas</label>
            <input type="number" name="capacity" id="capacity" min="1" max="200"
                   value="{{ old('capacity', $studyGroup?->capacity) }}"
                   class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
            @error('capacity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="classroom_id">Ruang Kelas</label>
            <select name="classroom_id" id="classroom_id"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                <option value="">-- belum ditentukan --</option>
                @foreach ($classrooms as $classroom)
                    <option value="{{ $classroom->id }}"
                        @selected(old('classroom_id', $studyGroup?->classroom_id) == $classroom->id)>
                        {{ $classroom->name }}
                    </option>
                @endforeach
            </select>
            @error('classroom_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1" for="homeroom_employee_id">Wali Kelas</label>
            <select name="homeroom_employee_id" id="homeroom_employee_id"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                <option value="">-- belum ditentukan --</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}"
                        @selected(old('homeroom_employee_id', $studyGroup?->homeroom_employee_id) == $employee->id)>
                        {{ $employee->full_name }}
                    </option>
                @endforeach
            </select>
            @error('homeroom_employee_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1" for="status">Status</label>
        <select name="status" id="status" class="rounded border border-gray-300 px-3 py-2 text-sm">
            @foreach (App\Enums\StudyGroupStatus::cases() as $status)
                <option value="{{ $status->value }}"
                    @selected(old('status', $studyGroup?->status?->value ?? 'active') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <button class="rounded bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">Simpan</button>
</form>