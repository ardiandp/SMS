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
- **Commit:** (isi saat commit)

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
