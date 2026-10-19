@extends('layouts.app')

@section('title', 'Portal')

@section('content')
    <div class="bg-white rounded shadow p-6">
        <h1 class="text-xl font-semibold mb-1">Portal Siswa &amp; Orang Tua</h1>

        @if ($students->isEmpty())
            <p class="mt-2 text-gray-600">Akun ini belum terhubung ke data siswa mana pun. Hubungi pihak sekolah untuk menghubungkan akun Anda.</p>
        @else
            <form method="GET" action="{{ route('portal.index') }}" class="mt-4 flex flex-wrap items-center gap-2">
                <label for="student" class="text-sm text-gray-600">Profil:</label>
                <select id="student" name="student" onchange="this.form.submit()"
                    class="rounded border border-gray-300 px-3 py-2 text-sm">
                    @foreach ($students as $profile)
                        <option value="{{ $profile->id }}" @selected($activeStudent?->id === $profile->id)>
                            {{ $profile->full_name }} ({{ $profile->nis }})
                        </option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="text-sm text-indigo-600 hover:underline">Tampilkan</button></noscript>
            </form>

            @if ($activeStudent)
                <nav aria-label="Menu portal" class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-2 text-sm">
                    <a href="{{ route('portal.schedule', $activeStudent) }}" class="rounded border border-gray-200 px-3 py-2 hover:border-indigo-300 hover:text-indigo-600">Jadwal</a>
                    <a href="{{ route('portal.attendance', $activeStudent) }}" class="rounded border border-gray-200 px-3 py-2 hover:border-indigo-300 hover:text-indigo-600">Absensi</a>
                    <a href="{{ route('portal.grades', $activeStudent) }}" class="rounded border border-gray-200 px-3 py-2 hover:border-indigo-300 hover:text-indigo-600">Nilai</a>
                    <a href="{{ route('portal.report-cards', $activeStudent) }}" class="rounded border border-gray-200 px-3 py-2 hover:border-indigo-300 hover:text-indigo-600">Rapor</a>
                    <a href="{{ route('portal.announcements', $activeStudent) }}" class="rounded border border-gray-200 px-3 py-2 hover:border-indigo-300 hover:text-indigo-600">Pengumuman</a>
                </nav>
            @endif
        @endif
    </div>
@endsection
