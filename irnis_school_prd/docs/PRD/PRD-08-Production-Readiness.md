# PRD 08 — Production Readiness & Hardening

## Tujuan
Memastikan platform siap digunakan oleh sekolah secara operasional dan aman.

## Scope
- Backup otomatis, enkripsi backup, lokasi terpisah, dan uji restore.
- Monitoring error, health check, queue failure dan kapasitas.
- Kebijakan retensi data, ekspor, koreksi, dan penghapusan.
- Audit log untuk perubahan sensitif.
- Optimasi query, indeks, eager loading, pagination dan cache terukur.
- Pemeriksaan akses file, session, rate limit, CSRF, XSS, dependency/security updates.
- Deployment checklist, rollback plan dan staging.

## Dependencies
Fondasi PRD 00; idealnya modul yang akan dipakai sekolah telah lulus acceptance criteria.

## Acceptance criteria
- Restore drill berhasil di environment terisolasi dan terdokumentasi.
- Tidak ada secret di repository/log.
- Uji cross-tenant dilakukan pada setiap domain kritis.
- Query lambat utama diukur dan dioptimasi berdasarkan bukti.
- Prosedur deploy/rollback dan incident response dasar terdokumentasi.
- Export data sensitif tercatat dan dibatasi.
- Pengujian smoke test dilakukan setelah deploy.

## Prompt OpenCode
Lakukan audit kesiapan produksi tanpa mengubah data produksi. Buat daftar temuan dengan severity, bukti, dampak dan rekomendasi. Perbaiki hanya setelah perubahan disetujui. Jangan mengklaim aman hanya karena test lolos; sertakan keterbatasan pemeriksaan. Uji backup/restore hanya di lingkungan terisolasi.
