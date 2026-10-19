# CATATAN — Log Progres Pengembangan SMS

> **Konvensi:** Setiap kali satu task selesai (fitur/fix, sudah diverifikasi),
> catat ringkasannya di bagian **Progres** (paling baru di atas) lalu **commit**
> beserta file catatan ini, agar riwayat pengerjaan mudah diikuti lewat
> `git log` tanpa harus membaca seluruh diff.

Format entri:

```
### [ID] Judul task — status
- **Apa:** perubahan inti (1–3 baris)
- **File:** daftar file yang disentuh
- **Verifikasi:** perintah/test yang dijalankan + hasilnya
- **Commit:** <hash> (diisi setelah commit)
```

---

## Progres

### [ID] PRD-06: Portal Siswa & Orang Tua — SELESAI
- **Apa:** Portal read-only dengan relasi akun yang terverifikasi di server:
  - Migration `student_user_links` + `guardian_user_links` (satu akun satu
    profil per tenant; wali multi-anak lewat `student_guardians`).
  - Role `student`/`parent` + permission `portal.access` (gerbang shell;
    otorisasi baris data per-request oleh `PortalAccess`, bukan policy Gate).
  - `PortalController` + 6 view: jadwal (rombel aktif), absensi, nilai
    **finalized saja**, rapor **published saja**, pengumuman, beranda dengan
    switch profil `?student=` (ID tak terotorisasi → 403, bukan fallback).
  - Pemulihan akses (lupa password bawaan Laravel, token 60 menit) +
    `PortalLinkService::revoke()` dengan audit.
  - DemoSeeder: akun demo siswa + wali multi-anak.
- **File:** `app/Domain/Portal/{PortalAccess,PortalLinkService}.php`,
  `app/Http/Controllers/{PortalController,Auth/PasswordResetController}.php`,
  `app/Models/{StudentUserLink,GuardianUserLink}.php`, 2 migration, 2 factory,
  `routes/web.php`, `resources/views/portal/*`, `tests/Feature/PortalTest.php`
  (6 test: guardian-link, unrelated-403, student-isolation, draft-hidden,
  revocation, tenant-isolation), `database/seeders/{RoleSeeder,DemoSeeder}.php`.
- **Verifikasi:** `PortalTest` **6/6 lulus**; suite penuh **286/286 lulus**
  (1021 assertions, naik dari 280).
- **Commit:** `b013130`

### [ID] PRD-05: test acceptance penutup (dashboard audience, duplikat nomor, snapshot surat) — SELESAI
- **Apa:** Menutup test gap sesuai daftar Tests PRD-05 yang belum ter-cover:
  - Dashboard menyembunyikan pengumuman ber-audience `role` dari role lain
    (`AnnouncementTest::test_dashboard_hides_role_scoped_announcement_from_other_roles`).
  - Nomor surat duplikat ditolak dalam tenant yang sama, tetapi boleh dipakai
    ulang di tenant lain — membuktikan constraint unik per-tenant
    (`LetterTest::test_duplicate_letter_number_is_rejected_within_tenant`).
  - Snapshot surat terbit tidak berubah setelah template direvisi
    (`LetterTest::test_issued_letter_snapshot_survives_template_revision`).
  - Kode template duplikat wajib lewat alur revisi
    (`LetterTest::test_duplicate_template_code_requires_revision`).
  - Tidak ada perubahan kode produksi — hanya test (fitur sudah benar).
- **File:** `tests/Feature/AnnouncementTest.php`, `tests/Feature/LetterTest.php`.
- **Verifikasi:** 4 test baru **4/4 lulus** (23 assertions); suite penuh
  **280/280 lulus** (1001 assertions, naik dari 276 — tidak ada test lama rusak).
- **Commit:** `adbd112` (+ commit CATATAN menyusul)

### [ID] PRD-06: notifikasi audience study_group (portali siswa + wali) — SELESAI
- **Apa:** Lanjutkan tugas `study_group` untuk notifikasi:
  - `NotificationDispatcher::studyGroupUsers()` — penerima audience `study_group`:
    - Siswa: akun portal (`StudentUserLink`) yang penempatannya aktif
      (`PlacementStatus::Active`) di rombel.
    - Wali: akun portal (`GuardianUserLink`) yang terhubung ke siswa dalam
      rombel dan **mengaktifkan** `receives_notifications` di pivot
      `student_guardians` (preferensi komunikasi wali).
  - Payload tetap minimal: judul + link saja (anti jalur bocor data).
- **File:** `app/Domain/Administration/NotificationDispatcher.php`
  (`studyGroupUsers()`), `app/Models/StudentGuardian.php`, `app/Models/Guardian.php`.
- **Verifikasi:** `php artisan test` (suite penuh); review kode against
  `NotificationTest` (6 test) dan `PortalTest` (6 test).
- **Commit:** `33be252`

### [ID] PRD-05: laporan administratif generik + ekspor CSV — SELESAI
- **Apa:** Sesuai pilihan scope: laporan berbasis registry/domain dengan
  pemilihan kolom dan filter periode.
  - `ReportCatalog` (registry whitelist di server): 3 laporan awal —
    `students` (permission `view.students`), `letters` (`manage.letters`),
    `announcements` (`manage.announcements`). Tiap definisi memuat model
    (otomatis SchoolScope), kolom yang boleh dipilih + penerjemah nilai, dan
    kolom dasar periode. **Input user tidak pernah jadi nama tabel/kolom
    query** — hanya kunci whitelist yang dikenal (tolak injeksi skema).
  - `ReportController`: `/reports` (daftar berotorisasi), `/reports/{report}`
    (tabel + paginasi 20 + form periode/kolom), `/reports/{report}/export`
    (CSV streaming `chunkById`, ordering di-reorder dulu agar chunking benar).
  - **Export limit**: default 5.000 baris (config `reports.export_limit`);
    di atas itu ekspor ditolak dengan pesan mempersempit periode.
  - Nav "Laporan" muncul jika user punya salah satu permission laporan.
- **File:** `app/Domain/Administration/ReportCatalog.php`,
  `app/Http/Controllers/ReportController.php`, `routes/web.php`,
  `resources/views/layouts/app.blade.php`,
  `resources/views/reports/{index,show}.blade.php`,
  `tests/Feature/ReportTest.php` (8 test: daftar-berotorisasi, filter
  periode+kolom, key tak dikenal 404, tanpa permission 403, kolom tak
  dikenal ditolak, isolasi tenant, ekspor CSV dalam batas, ekspor ditolak
  saat melebihi batas).
- **Verifikasi:** `ReportTest` + `ReportCardTest` **19/19 lulus**; suite
  penuh **276/276 lulus** (978 assertions).
- **Commit:** `505f773` (+ commit CATATAN menyusul)

### [ID] PRD-05: notifikasi in-app — SELESAI
- **Apa:** Melengkapi scope PRD-05 yang masih kosong:
  - Migration `notifications` (skema baku Laravel: uuid, type, morph
    notifiable, data, read_at) **+ kolom `school_id`** sehingga baris ikut
    ter-scope SchoolScope — notifikasi tidak pernah terbaca lintas tenant.
  - Model `Notification` (extends `DatabaseNotification`) dengan auto-fill
    `school_id` dari konteks tenant; relasi `User::notifications()` di-override
    memakai model ini.
  - `NotificationDispatcher` di service: pengumuman terbit → notif anggota
    sekolah sesuai audience eksplisit (`all`, `role`; `study_group` → akun
    portal siswa + wali yang menerima notifikasi, PRD-06); surat diterbitkan →
    notif role `school-admin`/`super-admin`. Pelaku aksi tidak dinotifikasi;
    trigger dijalankan di dalam transaksi agar atomic.
  - Payload **hanya judul + link** (tanpa body pengumuman / body_snapshot
    surat) — anti "jalur bocor data".
  - UI lonceng (hitung unread) di layout + halaman `/notifications` +
    tandai-sudah-dibaca (cek kepemilikan eksplisit → 404 untuk notif user lain).
- **File:** migration `create_notifications_table`, `app/Models/Notification.php`,
  `app/Models/User.php`, `app/Notifications/{AnnouncementPublished,LetterIssued}.php`,
  `app/Domain/Administration/NotificationDispatcher.php`,
  `app/Domain/Administration/{AnnouncementService,SchoolLetterService}.php`,
  `app/Http/Controllers/NotificationController.php`, `routes/web.php`,
  `resources/views/layouts/app.blade.php`,
  `resources/views/notifications/index.blade.php`,
  `tests/Feature/NotificationTest.php` (6 test).
- **Verifikasi:** `NotificationTest` **6/6 lulus**; `php artisan migrate`
  (DB dev) sukses; suite penuh **268/268 lulus** (nol regresi).
- **Commit:** d0628b7

### [ID] Fix RoleSeeder gagal saat `php artisan migrate:fresh --seed` — SELESAI
- **Apa:** `DatabaseSeeder` memakai `WithoutModelEvents`, sehingga event model
  (termasuk flush otomatis cache Spatie saat `Permission` dibuat) tidak pernah
  terpicu. Akibatnya `PermissionRegistrar` menyajikan daftar permission **kosong**
  dari cache → `Role::syncPermissions()` melempar
  `There is no permission named 'view.dashboard'` padahal row sudah ada di DB.
  Diperbaiki dengan flush eksplisit `forgetCachedPermissions()` di `RoleSeeder`
  sebelum `syncPermissions()` tiap role.
- **File:** `database/seeders/RoleSeeder.php`
- **Verifikasi:** `php artisan migrate:fresh --seed` sukses (30 permissions,
  4 roles, 73 role↔permission); `php artisan test` **262/262 passed**.
- **Commit:** bb68487

### [ID] Fix bug manajemen LetterTemplate & SchoolLetter (permission/policy/view) — SELESAI
- **Apa:** Perbaikan rangkaian bug agar modul surat berfungsi:
  - `CurrentSchool`: `unsetRelation('roles')` setelah `setPermissionsTeamId()`
    supaya relasi roles tidak stale (bug utama `can('manage.templates')` = false).
  - `SchoolLetterPolicy` berisi duplikat class `LetterTemplatePolicy`
    (copy-paste) → fatal PHP. Dihapus; policy `update`/`issue` wajib status
    Draft, `cancel` wajib Issued.
  - Nama parameter route model binding tidak cocok dengan type-hint
    (`$template`→`$letterTemplate`, `$letter`→`$schoolLetter`) → controller
    menerima `null` → 403/“not draft” palsu.
  - Form request: closure `static` berisi `$this`, `authorize()` dibedakan
    untuk create vs update, rule `code` hanya `required` saat POST (revisi
    memakai code lama).
  - `SchoolLetterController::index()` menyiapkan query `templates` untuk view.
  - Import yang hilang di policy/request; perbaikan asersi enum-vs-string
    di `LetterTest` (`->status->value` → `->status`).
  Revisi terakhir: flush eksplisit cache Spatie → commit `bb68487`.
- **File:** `CurrentSchool.php`, `SchoolLetterPolicy.php`,
  `LetterTemplateController.php`, `SchoolLetterController.php`,
  `StoreLetterTemplateRequest.php`, `StoreSchoolLetterRequest.php`,
  `LetterTest.php`, `database/seeders/RoleSeeder.php`
- **File:** `CurrentSchool.php`, `SchoolLetterPolicy.php`,
  `LetterTemplateController.php`, `SchoolLetterController.php`,
  `StoreLetterTemplateRequest.php`, `StoreSchoolLetterRequest.php`,
  `LetterTest.php`
- **Verifikasi:** `LetterTest` 13/13; suite penuh **262/262 passed**.
- **Commit:** 9074c8c (bersama fitur letters)

---

## Status terkini

| Item | Status |
|---|---|
| Test suite (262 test) | ✅ semua lulus (assertions 929) |
| `migrate:fresh --seed` (DB `sms`) | ✅ sukses, 30 permission / 4 role |
| Modul letters (template + surat) | ✅ fungsional, 13 test lulus |

## Catatan teknis penting (untuk pencegahan bug serupa)

- **Spatie + tenancy:** setelah `setPermissionsTeamId()` wajib
  `$user->unsetRelation('roles')`, karena relasi di-cache saat team null.
- **Route model binding:** nama variabel di signature method **harus sama**
  persis dengan nama parameter di `Route::get(..., '{x}')`.
- **Seeder vs model events:** saat seeder dipanggil lewat `DatabaseSeeder`
  (pakai `WithoutModelEvents`), event `created/updated/deleted` mati — komponen
  yang mengandalkan event untuk invalidasi cache harus di-flush manual.

## Langkah berikutnya

- (isi oleh sesi berikutnya)
