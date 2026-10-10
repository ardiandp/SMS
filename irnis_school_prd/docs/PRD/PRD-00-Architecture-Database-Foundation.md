# PRD 00 — Architecture & Database Foundation

## Tujuan
Menyiapkan fondasi SaaS multi-tenant Laravel sebelum modul sekolah dibangun.

## Scope
- Bootstrap Laravel dan konfigurasi environment.
- MySQL connection, migration baseline, health checks.
- Tenant model `schools` dan membership user-school.
- Authentication, tenant selection, role/permission baseline.
- Audit log untuk aksi sensitif.
- Tailwind/Blade/Livewire layout dasar.
- CI/test commands, formatter, seeders referensi.
- Dokumentasi keputusan arsitektur.

## Out of scope
Fitur siswa/akademik lengkap, payment gateway, AI.

## Dependencies
Tidak ada.

## Data/schema
`schools`, `users`, `school_user_memberships`, roles/permissions, `audit_logs`. Tambahkan `status`, timezone, slug dan timestamps sesuai data dictionary.

## Acceptance criteria
- Aplikasi berjalan pada environment development dari README.
- Migration berjalan dari database kosong.
- User dapat login/logout dengan aman.
- User hanya dapat memilih tenant tempat ia memiliki membership aktif.
- Permintaan tenant tanpa membership ditolak.
- Perubahan role/membership penting dicatat ke audit log tanpa menyimpan rahasia.
- Test membuktikan user tenant A tidak dapat membaca tenant B.
- `.env.example` tidak berisi secret nyata.
- Test suite dan formatter dapat dijalankan melalui instruksi yang terdokumentasi.

## Security
Hash password melalui Laravel; rate limit login; CSRF protection; session secure settings; jangan percaya `school_id` dari request; policy server-side; least privilege.

## Tests
Auth happy/negative paths, tenant switch, inactive membership, cross-tenant access, audit event, fresh migration.

## Prompt OpenCode
Baca AGENTS.md, ARCHITECTURE.md, DATA_DICTIONARY.md, dan PRD-00. Audit kondisi repo dahulu. Ajukan rencana file dan migration sebelum coding. Implementasikan fondasi saja: schools, users/auth yang dibutuhkan, memberships, role/permission, tenant resolution, audit log, layout dasar, dan tests. Jangan membuat modul siswa/akademik atau AI. Jalankan migration pada database test kosong dan test relevan; laporkan hasil nyata dan risiko.
