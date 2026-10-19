@if ($students->count() > 1)
    <form method="GET" action="{{ route('portal.index') }}" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <label for="portal-student" class="text-gray-600">Profil:</label>
        <select id="portal-student" name="student" onchange="this.form.submit()"
            class="rounded border border-gray-300 px-3 py-2">
            @foreach ($students as $profile)
                <option value="{{ $profile->id }}" @selected($student->id === $profile->id)>
                    {{ $profile->full_name }} ({{ $profile->nis }})
                </option>
            @endforeach
        </select>
        <noscript><button type="submit" class="text-indigo-600 hover:underline">Tampilkan</button></noscript>
    </form>
@endif

<nav aria-label="Menu portal" class="mb-4 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('portal.schedule', $student) }}" class="rounded border px-3 py-1 {{ request()->routeIs('portal.schedule') ? 'border-indigo-400 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-indigo-300' }}">Jadwal</a>
    <a href="{{ route('portal.attendance', $student) }}" class="rounded border px-3 py-1 {{ request()->routeIs('portal.attendance') ? 'border-indigo-400 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-indigo-300' }}">Absensi</a>
    <a href="{{ route('portal.grades', $student) }}" class="rounded border px-3 py-1 {{ request()->routeIs('portal.grades') ? 'border-indigo-400 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-indigo-300' }}">Nilai</a>
    <a href="{{ route('portal.report-cards', $student) }}" class="rounded border px-3 py-1 {{ request()->routeIs('portal.report-cards') ? 'border-indigo-400 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-indigo-300' }}">Rapor</a>
    <a href="{{ route('portal.announcements', $student) }}" class="rounded border px-3 py-1 {{ request()->routeIs('portal.announcements') ? 'border-indigo-400 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-indigo-300' }}">Pengumuman</a>
</nav>
