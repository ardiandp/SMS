# Uji Restore Drill — PRD-08

> Uji restore **harus dilakukan di environment terisolasi**, bukan produksi.
> Jangan mengubah data produksi selama uji ini.

## Tujuan

Memastikan backup database dapat dipulihkan dengan benar dan data utuh
setelah restore, sehingga RTO/RPO dapat dipenuhi saat災害 terjadi.

## Langkah Uji

1. **Siapkan environment terisolasi**
   - Clone produksi ke staging/development yang terisolasi.
   - Pastikan tidak ada koneksi ke data produksi asli.

2. **Siapkan backup**
   - Ambil hasil backup terbaru dari `scripts/backup-db.sh`.
   - Jika terenkripsi, dekripsi dengan `ENCRYPTION_KEY` yang sama:
     ```bash
     openssl enc -d -aes-256-cbc -pbkdf2 \
         -in backup_file.sql.gz.enc -out backup_file.sql.gz -pass pass:ENCRYPTION_KEY
     ```

3. **Restore database**
   - Untuk SQLite:
     ```bash
     cp backup_file.sqlite database/database.sqlite
     php artisan migrate --force
     ```
   - Untuk MySQL:
     ```bash
     gunzip -c backup_file.sql.gz | mysql -u USER -p DB_NAME
     php artisan migrate --force
     ```

4. **Verifikasi integritas data**
   - Hitung jumlah siswa, nilai, absensi sebelum dan setelah restore.
   - Pastikan tidak ada data yang hilang atau rusak.
   - Coba login sebagai beberapa user berbeda sekolah (isolasi tenant).

5. **Catatan hasil uji**
   - Waktu mulai dan selesai restore (untuk RTO).
   - Titik data paling baru yang terpulih (untuk RPO).
   - Masalah yang ditemukan dan penanganannya.
   - Tanda tangan penguji dan tanggal.

## Frekuensi

Uji restore drill dilakukan **minimal sekali per bulan** atau setiap ada
perubahan besar pada skema database.

## Dokumen Terkait

- `scripts/backup-db.sh` — skrip backup otomatis.
- `irnis_school_prd/docs/PRD/PRD-08-Production-Readiness.md` — PRD asli.
- `irnis_school_prd/docs/PRD/PRD-08-Audit-Findings.md` — temuan audit.
