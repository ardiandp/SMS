# PRD 03 — Absensi Siswa

## Tujuan
Mencatat dan merekap kehadiran siswa secara konsisten.

## Scope
- Konfigurasi jenis sesi absensi: harian dan/atau per pelajaran (keputusan sekolah).
- Membuat sesi absensi per rombel/tanggal atau jadwal.
- Status hadir, sakit, izin, alpa, dan status tambahan yang disetujui sekolah.
- Koreksi absensi oleh role berwenang dengan audit.
- Alasan/bukti izin bila diaktifkan.
- Rekap harian, per siswa, rombel, periode, dan ekspor terbatas.

## Dependencies
PRD 00–02.

## Schema
`attendance_sessions`, `attendance_records`, `attendance_excuses`.

## Acceptance criteria
- Setiap record terkait sesi dan siswa yang valid dalam rombel/scope sesi.
- Unique constraint mencegah record ganda untuk siswa pada sesi yang sama.
- Guru hanya dapat mengelola sesi yang menjadi kewenangannya.
- Koreksi menyimpan actor, waktu, dan perubahan yang relevan.
- Rekap menghitung jumlah/status dengan definisi yang terdokumentasi.
- Ekspor menerapkan tenant dan permission filter.

## Security
Batasi akses absensi berdasarkan assignment; file alasan private; jangan bocorkan daftar siswa melalui identifier yang ditebak.

## Tests
Duplicate attendance, status invalid, teacher outside assignment, correction audit, cross-tenant record, summary totals, export authorization.

## Prompt OpenCode
Bangun PRD-03 saja. Konfirmasikan dari konfigurasi apakah sekolah memakai absensi harian atau per jam; implementasikan desain yang tidak menggandakan catatan. Tambahkan transaksi, unique constraints, policies, audit perubahan, rekap dan tests. Jangan mengimplementasikan nilai atau AI.
