# PRD 02 — Struktur Akademik

## Tujuan
Mendefinisikan tahun ajaran, semester, jenjang, kelas, rombel, mata pelajaran, penugasan guru, dan jadwal.

## Scope
- Tahun ajaran dan semester dengan tanggal mulai/akhir.
- Jenjang yang dapat dikonfigurasi sekolah.
- Ruang kelas.
- Rombongan belajar per tahun ajaran.
- Keanggotaan siswa dalam rombel dengan tanggal mulai/akhir dan status.
- Mata pelajaran dan penugasan guru.
- Jadwal harian/mingguan dan pemeriksaan konflik dasar.

## Dependencies
PRD 00 dan PRD 01.

## Schema
`academic_years`, `semesters`, `grade_levels`, `classrooms`, `study_groups`, `study_group_members`, `subjects`, `teaching_assignments`, `class_schedules`.

## Acceptance criteria
- Sekolah dapat mengatur beberapa tahun ajaran dan semester tanpa menghapus sejarah.
- Jenjang tidak di-hard-code.
- Rombel selalu terkait tahun ajaran dan jenjang.
- Penempatan siswa dapat ditutup dan dibuat ulang untuk tahun berikutnya dengan histori.
- Penugasan menghubungkan guru, mata pelajaran, rombel dan periode.
- Sistem memperingatkan/menolak konflik jadwal berdasarkan aturan yang disepakati.
- Data dari sekolah lain tidak dapat direferensikan.

## Security
Policies untuk admin/kepala sekolah/guru; validasi tenant consistency pada seluruh FK; batasi pengubahan tahun ajaran aktif.

## Tests
Tanggal invalid, duplicate group name per year, membership historis, double active placement, teacher/room schedule conflict, cross-tenant foreign reference.

## Prompt OpenCode
Implementasikan PRD-02 berdasarkan ERD/data dictionary. Jangan menyimpan `class_id` permanen pada students. Buat histori melalui study_group_members. Validasi tanggal dan konflik jadwal. Jelaskan constraint yang tidak dapat dijamin langsung oleh MySQL dan enforce lewat service/transaksi plus tests. Jangan membuat modul absensi/nilai.
