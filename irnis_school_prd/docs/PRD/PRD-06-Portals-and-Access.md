# PRD 06 — Portal Siswa & Orang Tua

## Tujuan
Memberi siswa dan wali akses mandiri yang aman terhadap informasi miliknya.

## Scope
- Portal responsif web.
- Relasi akun pengguna ke siswa dan/atau wali.
- Tampilan jadwal, pengumuman, absensi, nilai, dan rapor yang dipublikasikan.
- Notifikasi dan preferensi komunikasi dasar.
- Pengelolaan sesi dan proses pemulihan akses.

## Dependencies
PRD 00–05.

## Schema
Gunakan `users`, `school_user_memberships`, `student_guardians`, dan tabel domain yang ada. Tambahkan tabel relasi akun-siswa khusus hanya bila kebutuhan tidak tercakup oleh relasi tersebut.

## Acceptance criteria
- Siswa hanya dapat melihat data dirinya.
- Wali hanya dapat melihat siswa yang terhubung dan diizinkan.
- Wali dengan beberapa anak dapat berpindah profil tanpa melewati policy.
- Nilai/rapor draft tidak tampil sebelum dipublikasikan.
- Semua endpoint diuji terhadap IDOR dan cross-tenant access.
- UI mobile-friendly dan dapat digunakan dengan keyboard.

## Security
Verifikasi hubungan guardian-student di server pada setiap request; rate limit autentikasi; sesi aman; jangan memakai ID acak sebagai pengganti authorization.

## Tests
Guardian sees linked child, guardian cannot see unrelated child, student cannot see another student, unpublished report denial, account revocation, tenant isolation.

## Prompt OpenCode
Bangun portal PRD-06 memakai data dan policy yang sudah ada. Jangan menambahkan API atau tabel duplikat jika relasi yang tersedia mencukupi. Buat test negatif eksplisit untuk akses anak yang tidak terkait dan rapor yang belum dipublikasikan.
