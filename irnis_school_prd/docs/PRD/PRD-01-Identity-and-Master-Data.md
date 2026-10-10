# PRD 01 — Identitas & Data Master

## Tujuan
Mengelola profil sekolah, siswa, orang tua/wali, guru, dan staf secara terpusat.

## Scope
- Profil sekolah dan konfigurasi jenjang.
- CRUD siswa, status, nomor induk, pencarian dan pagination.
- Data orang tua/wali dan relasi banyak-ke-banyak dengan siswa.
- Data pegawai dan pemetaan akun pengguna.
- Impor CSV/XLSX melalui proses preview, validasi, error report, dan konfirmasi.
- Detail data siswa dan riwayat status dasar.

## Dependencies
PRD 00.

## Schema
`students`, `guardians`, `student_guardians`, `student_documents`, `employees`, `employee_user_links`, `employee_assignments`.

## Acceptance criteria
- Admin sekolah dapat membuat, melihat, memperbarui, dan menonaktifkan record sesuai permission.
- NIS unik dalam satu sekolah.
- Seorang siswa dapat memiliki lebih dari satu wali; seorang wali dapat terhubung ke lebih dari satu siswa.
- Impor menampilkan preview dan error per baris sebelum data ditulis.
- Pencarian/pagination berjalan server-side.
- File sensitif tidak dapat diakses lewat URL publik.
- Perubahan data sensitif dicatat sesuai audit policy.

## Security
Tenant scoping untuk semua CRUD/import/export; validasi file, MIME dan ukuran; private storage; minimisasi data pribadi; hindari menampilkan NISN/telepon penuh pada log.

## Tests
Duplicate NIS same school, same NIS different school, guardian linking, cross-tenant import, invalid upload, permission matrix, pagination.

## Prompt OpenCode
Implementasikan PRD-01 saja di atas fondasi PRD-00. Sebelum coding, verifikasi skema existing agar tidak menggandakan tabel. Buat migrations, models, Form Requests, policies, services/actions bila diperlukan, Livewire screens, factories, dan tests. Impor harus punya preview/validasi dan tidak menulis sebagian data diam-diam. Uji tenant isolation pada setiap endpoint/action. Jangan implementasikan tahun ajaran atau absensi pada tahap ini.
