# IRNIS School Management Platform
## Paket PRD, Database Blueprint, dan OpenCode

**Target:** SaaS multi-tenant untuk banyak sekolah dan multi-jenjang  
**Stack:** Laravel, MySQL 8.4 LTS, Blade, Livewire, Tailwind CSS  
**Pendekatan:** modular monolith; AI provider-agnostic pada tahap akhir

### Cara menggunakan paket ini
1. Baca `docs/architecture/ARCHITECTURE.md`.
2. Tinjau `docs/database/ERD.md` dan `docs/database/DATA_DICTIONARY.md`.
3. Implementasikan PRD secara berurutan, mulai dari PRD 00.
4. Minta OpenCode membuat rencana terlebih dahulu, baru mengubah kode.
5. Jalankan test dan review diff sebelum merge/commit.

### Isi
- `docs/architecture/ARCHITECTURE.md`: keputusan arsitektur dan batas domain.
- `docs/database/ERD.md`: ERD konseptual berbasis Mermaid.
- `docs/database/DATA_DICTIONARY.md`: tabel inti, kolom, constraint, dan indeks.
- `docs/database/MIGRATION_PLAN.md`: urutan migration dan strategi perubahan skema.
- `docs/PRD/PRD-00` sampai `PRD-09`: spesifikasi tahap, dependensi, acceptance criteria, keamanan, test, dan prompt OpenCode.
- `AGENTS.md`: instruksi permanen untuk coding agent.

### Batasan dan asumsi
Dokumen ini adalah blueprint awal yang diturunkan dari cakupan proposal IRNIS School Digitalization. Proposal memperlihatkan data siswa, guru/staf, pengumuman, absensi, surat sekolah, tahun ajaran, AI School Assistant, kustomisasi, kelulusan, rapor, dashboard, kenaikan kelas, nilai, mata pelajaran, jadwal, kalender akademik, kelas/rombel, keamanan dan perlindungan data, serta modul tambahan seperti PPDB, LMS, keuangan, portal, perpustakaan, inventaris, dan HRIS. Detail operasional yang tidak ditentukan proposal diberi status **keputusan desain** dan harus divalidasi dengan sekolah.

Tidak semua tabel di dokumen ini harus dibuat pada migration pertama. Buat tabel sesuai tahap PRD dan jangan membuat modul tambahan sebelum aturan bisnisnya dikonfirmasi.
