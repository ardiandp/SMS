# PRD 05 — Dashboard & Administrasi

## Tujuan
Menyediakan ringkasan operasional dan alat komunikasi/administrasi sekolah.

## Scope
- Dashboard berbeda sesuai role.
- Kalender akademik dan kegiatan.
- Pengumuman dengan audience yang jelas, jadwal publikasi dan status.
- Template surat, nomor surat, draf, persetujuan bila diperlukan, penerbitan dan snapshot.
- Notifikasi in-app.
- Laporan administratif dengan filter periode dan ekspor terotorisasi.

## Dependencies
PRD 00–04.

## Schema
`academic_events`, `announcements`, `announcement_recipients`, `letter_templates`, `school_letters`, `notifications`.

## Acceptance criteria
- Dashboard hanya menampilkan metrik yang boleh diakses pengguna.
- Pengumuman memiliki status draft/published/archived dan audience.
- Surat terbit menyimpan nomor, tanggal, template/version, dan snapshot isi.
- Nomor surat unik dalam tenant.
- Notifikasi tidak menjadi jalur bocor data.
- Laporan menggunakan query terfilter dan pagination/queue untuk ekspor besar.

## Security
Sanitasi HTML/template, policy penerbitan, pembatasan audience, signed URL bila perlu, audit surat penting.

## Tests
Role dashboard, audience visibility, duplicate letter number, template versioning, XSS, export limits, tenant isolation.

## Prompt OpenCode
Implementasikan dashboard dan administrasi PRD-05. Dashboard harus menggunakan query agregasi yang terotorisasi, bukan memuat seluruh record. Gunakan snapshot untuk surat terbit dan sanitasi konten. Jangan mengimplementasikan modul finance/PPDB/LMS.
