# Migration Plan

## Aturan
1. Migration mengikuti urutan dependensi, bukan urutan halaman UI.
2. Gunakan nama migration berurutan dan deskriptif.
3. Migration harus dapat dijalankan pada database kosong.
4. Jangan mengedit migration yang sudah diterapkan ke shared/staging/production; buat migration baru.
5. Gunakan expand-and-contract untuk perubahan besar: tambah kolom/tabel, deploy kode kompatibel, backfill, verifikasi, lalu hapus struktur lama pada rilis terpisah.
6. Untuk tabel besar, evaluasi locking dan durasi migration sebelum production.
7. Jangan menjalankan `migrate:fresh` atau operasi destructive pada database yang berisi data penting.
8. Seeders produksi hanya untuk data referensi yang disepakati; jangan seed akun default dengan password tetap.
9. Backup dan uji restore sebelum migration berisiko.
10. Tambahkan test untuk constraints, relasi, dan tenant isolation.

## Urutan yang disarankan
### M00 — Platform foundation
- schools
- users (gunakan migration/framework auth bawaan bila tersedia)
- school_user_memberships
- roles/permissions sesuai package yang dipilih
- audit_logs
- tabel queue/cache/session jika menggunakan database driver

### M01 — Identity & school data
- students
- guardians
- student_guardians
- student_documents
- employees
- employee_user_links
- employee_assignments

### M02 — Academic structure
- academic_years
- semesters
- grade_levels
- classrooms
- study_groups
- study_group_members
- subjects
- teaching_assignments
- class_schedules

### M03 — Attendance
- attendance_sessions
- attendance_records
- attendance_excuses

### M04 — Assessment & reporting
- assessment_categories
- assessments
- assessment_scores
- grading_policies
- final_grades
- report_cards
- report_card_items
- promotion_decisions
- graduation_records

### M05 — Administration
- academic_events
- announcements
- announcement_recipients
- letter_templates
- school_letters
- notifications

### M06 — Portals & hardening
- tambahkan tabel yang benar-benar diperlukan untuk akun portal atau akses keluarga jika belum tertangani
- file access metadata bila dibutuhkan
- audit event taxonomy, data retention, export tracking

### M07 — Optional modules
- PPDB, LMS, finance, library, inventory, health/UKS, extracurricular, alumni, HR/payroll.
- Masing-masing modul wajib punya PRD, ERD, dan aturan bisnis sebelum migration.

### M08 — AI
- ai_conversations
- ai_messages
- ai_tool_runs
- ai_feedback bila diperlukan
- ai_usage_logs

## Checklist per migration
- [ ] Foreign keys dan delete behavior ditentukan.
- [ ] Unique constraints dan nullability ditentukan.
- [ ] Indexes mendukung query utama.
- [ ] Tenant consistency ditangani.
- [ ] Down migration aman dan realistis.
- [ ] Migration dites dari database kosong.
- [ ] Seeder/factory tidak menggunakan data pribadi nyata.
- [ ] Ada catatan deployment bila perlu backfill/locking.
