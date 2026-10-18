<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'IRNIS School')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="font-semibold text-indigo-600">IRNIS School</a>
            @auth
                @if (session('current_school_id'))
                    <div class="flex items-center gap-4 text-sm">
                        <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-indigo-600">Dashboard</a>
                        <a href="{{ route('students.index') }}" class="text-gray-600 hover:text-indigo-600">Siswa</a>
                        <a href="{{ route('guardians.index') }}" class="text-gray-600 hover:text-indigo-600">Wali</a>
                        <a href="{{ route('employees.index') }}" class="text-gray-600 hover:text-indigo-600">Pegawai</a>
                        @can('view.academics')
                            <a href="{{ route('academic-years.index') }}" class="text-gray-600 hover:text-indigo-600">Tahun Ajaran</a>
                            <a href="{{ route('study-groups.index') }}" class="text-gray-600 hover:text-indigo-600">Rombel</a>
                            <a href="{{ route('academics.grade-levels.index') }}" class="text-gray-600 hover:text-indigo-600">Kelas</a>
                            <a href="{{ route('academics.subjects.index') }}" class="text-gray-600 hover:text-indigo-600">Mapel</a>
                            <a href="{{ route('classrooms.index') }}" class="text-gray-600 hover:text-indigo-600">Ruang</a>
                        @endcan
                        @can('view.attendance')
                            <a href="{{ route('attendance.sessions.index') }}" class="text-gray-600 hover:text-indigo-600">Absensi</a>
                        @endcan
                        @can('viewAny', App\Models\Assessment::class)
                            <a href="{{ route('assessments.index') }}" class="text-gray-600 hover:text-indigo-600">Penilaian</a>
                        @endcan
                        @can('viewAny', App\Models\FinalGrade::class)
                            <a href="{{ route('final-grades.index') }}" class="text-gray-600 hover:text-indigo-600">Nilai Akhir</a>
                        @endcan
                        @can('viewAny', App\Models\ReportCard::class)
                            <a href="{{ route('report-cards.index') }}" class="text-gray-600 hover:text-indigo-600">Rapor</a>
                        @endcan
                        @can('viewAny', App\Models\PromotionDecision::class)
                            <a href="{{ route('promotions.index') }}" class="text-gray-600 hover:text-indigo-600">Kenaikan &amp; Kelulusan</a>
                        @endcan
                        <a href="{{ route('academic-events.index') }}" class="text-gray-600 hover:text-indigo-600">Kalender</a>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <a href="{{ route('announcements.index') }}" class="text-gray-600 hover:text-indigo-600">Pengumuman</a>
                        @php($unreadNotifications = auth()->user()->notifications()->whereNull('read_at')->count())
                        <a href="{{ route('notifications.index') }}" class="text-gray-600 hover:text-indigo-600">
                            Notifikasi{{ $unreadNotifications > 0 ? ' ('.$unreadNotifications.')' : '' }}
                        </a>
                        <a href="{{ route('letter_templates.index') }}" class="text-gray-600 hover:text-indigo-600">Template &amp; Surat</a>
                        @canany(['view.students', 'manage.letters', 'manage.announcements'])
                            <a href="{{ route('reports.index') }}" class="text-gray-600 hover:text-indigo-600">Laporan</a>
                        @endcanany
                        <a href="{{ route('schools.select') }}" class="text-gray-600 hover:text-indigo-600">Ganti Sekolah</a>
                    </div>
                @endif
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-gray-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600 hover:underline">Keluar</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded bg-green-50 border border-green-200 text-green-800 px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>