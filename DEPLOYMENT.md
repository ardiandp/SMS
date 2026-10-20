# Deployment Checklist — SMS (PRD-08)

Dokumen ini mencakup prosedur deploy, rollback, dan incident response dasar
untuk aplikasi SMS. Dicatat sebagai bagian dari PRD-08 Production Readiness.

---

## Sebelum Deploy

- [ ] `APP_KEY` diisi di environment target (`php artisan key:generate`).
- [ ] `APP_DEBUG=false` di production.
- [ ] `SESSION_ENCRYPT=true` dan `SESSION_SECURE_COOKIE=true`.
- [ ] Semua migration sudah di-review dan tidak ada migration yang merusak data.
- [ ] Secret (DB password, AWS keys, dll.) tidak tersimpan di repository.
- [ ] Backup database produksi telah dibuat sebelum deploy.
- [ ] Uji backup/restore telah dilakukan di environment terisolasi (lihat `scripts/restore-drill.md`).
- [ ] Composer dependencies di-update dan tidak ada vulnerability kritis (`composer audit`).
- [ ] Semua test lulus di environment staging (`php artisan test`).

---

## Prosedur Deploy

1. Aktifkan maintenance mode:
   ```bash
   php artisan down --message="Sistem sedang diperbarui" --retry=60
   ```
2. Pull kode terbaru dari branch `main`.
3. Install dependency:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```
4. Jalankan migration:
   ```bash
   php artisan migrate --force
   ```
5. Cache konfigurasi dan route:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
6. Jalankan seeder jika diperlukan:
   ```bash
   php artisan db:seed --class=DemoSeeder  # hanya staging/development
   ```
7. Nonaktifkan maintenance mode:
   ```bash
   php artisan up
   ```
8. Verifikasi health check:
   ```bash
   curl https://example.com/health
   ```

---

## Rollback

Jika deploy gagal:

1. Kembalikan kode ke commit sebelumnya:
   ```bash
   git revert <commit-batal> --no-edit
   git push
   ```
2. Jika migration sudah dijalankan dan bermasalah, rollback migration:
   ```bash
   php artisan migrate:rollback --step=1 --force
   ```
3. Restore database dari backup terbaru (jika data rusak):
   - Lihat `scripts/restore-drill.md` untuk panduan restore di environment terisolasi.
4. Nonaktifkan maintenance mode jika masih aktif:
   ```bash
   php artisan up
   ```

---

## Incident Response Dasar

| Insiden | Tindakan Pertama | Penanggung Jawab |
|---------|-----------------|------------------|
| Sit.down / error 500 | Cek `storage/logs/laravel.log` dan health check `/health`. | Tim operasi |
| Data bocor lintas tenant | Segera nonaktifkan akses, cek `ResolveSchoolContext` middleware, audit `AuditLog`. | Keamanan |
| Gagal migrasi | Rollback migration, restore database dari backup. | Database admin |
| Queue gagal | Cek `failed_jobs` tabel, restart queue worker. | Tim operasi |

---

## Smoke Test Post-Deploy

Setelah deploy, jalankan verifikasi cepat:

- [ ] `/health` mengembalikan status `ok` (database, cache, queue terhubung).
- [ ] Halaman login dapat diakses (status 200).
- [ ] User dapat switch tenant dan melihat dashboard sekolahnya.
- [ ] Tidak ada siswa dari sekolah lain yang terlihat saat tenant aktif.

Smoke test juga tersedia sebagai otomasi:
```bash
php artisan test --filter=SmokeTest
```

---

## Dokumen Terkait

- `scripts/backup-db.sh` — skrip backup otomatis.
- `scripts/restore-drill.md` — panduan uji restore.
- `irnis_school_prd/docs/PRD/PRD-08-Production-Readiness.md` — PRD asli.
- `irnis_school_prd/docs/PRD/PRD-08-Audit-Findings.md` — temuan audit.
