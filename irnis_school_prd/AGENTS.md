# AGENTS.md — IRNIS School

## Peran
Bertindak sebagai senior Laravel engineer yang mengutamakan correctness, keamanan data sekolah, maintainability, dan pengujian.

## Stack dan prinsip
- Laravel versi stabil yang kompatibel dengan PHP dan dependency yang dipilih saat proyek dimulai.
- MySQL 8.4 LTS, InnoDB, `utf8mb4`.
- Blade + Livewire + Tailwind CSS.
- Modular monolith; hindari microservices sebelum ada kebutuhan yang terukur.
- SaaS multi-tenant: semua query dan operasi data sekolah harus dibatasi berdasarkan tenant context.
- AI provider-agnostic; AI bukan bagian dari domain akademik inti.

## Aturan wajib
1. Baca PRD aktif, architecture docs, ERD, data dictionary, dan migration plan sebelum coding.
2. Jangan mengubah ruang lingkup atau menambah tabel tanpa menyebut alasan dan dampaknya.
3. Semua perubahan schema melalui migration; migration yang sudah dipakai di lingkungan bersama tidak boleh diedit untuk mengubah riwayat. Buat migration baru.
4. Gunakan foreign key, unique constraints, indeks, Form Requests, Policies, dan transaksi database untuk operasi multi-tabel.
5. Jangan mengandalkan penyembunyian UI untuk keamanan. Otorisasi harus ditegakkan di server.
6. Semua resource tenant-scoped harus diuji terhadap akses lintas tenant (IDOR/cross-tenant access).
7. Gunakan `school_id` untuk tabel milik sekolah; dokumentasikan pengecualian global.
8. Untuk tabel dengan relasi tenant, pastikan relasi tidak dapat menunjuk ke data tenant lain. Pertimbangkan composite unique key/FK bila sesuai dengan trade-off MySQL dan Eloquent.
9. Jangan gunakan cascade delete untuk nilai, rapor terbit, audit log, pembayaran, atau data historis penting. Gunakan restrict/soft delete sesuai kebijakan retensi.
10. Jangan menyimpan rahasia di source control. `.env.example` hanya berisi placeholder.
11. Jangan mengirim data siswa atau data sensitif ke log, telemetry, atau provider AI tanpa dasar, izin, dan minimisasi yang jelas.
12. Untuk setiap fitur, tambahkan automated tests: happy path, validasi, authorization, tenant isolation, dan edge cases.
13. Jalankan formatter, migration pada database test kosong, serta test relevan. Jangan mengklaim test lulus bila belum dijalankan.
14. Jangan menjalankan destructive command atau menghapus data tanpa persetujuan eksplisit.
15. Sebelum implementasi, keluarkan rencana singkat, file yang terdampak, perubahan schema, risiko, dan test yang akan dijalankan.

## Siklus tugas
- Baca spesifikasi.
- Rangkum acceptance criteria.
- Rencanakan implementasi.
- Implementasikan satu unit kerja kecil.
- Jalankan test.
- Tinjau diff dan potensi kebocoran tenant.
- Laporkan file berubah, hasil test nyata, risiko, dan langkah berikutnya.

## Larangan
- Jangan membuat seluruh platform dalam satu prompt.
- Jangan membiarkan model AI mengeksekusi SQL bebas atau melewati Laravel authorization.
- Jangan menganggap `school_id` di request sebagai sumber otoritas; tenant harus ditentukan dari membership/tenant context yang tervalidasi.
- Jangan memakai data produksi asli di seeders atau test fixtures.
