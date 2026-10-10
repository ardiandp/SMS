# Roadmap PRD

| Tahap | Nama | Dependensi | Hasil utama |
|---|---|---|---|
| PRD 00 | Architecture & Database Foundation | — | SaaS tenant foundation, auth, roles, audit, test setup |
| PRD 01 | Identitas & Data Master | 00 | siswa, wali, guru/staf, import |
| PRD 02 | Struktur Akademik | 00–01 | tahun ajaran, semester, kelas, rombel, mapel, jadwal |
| PRD 03 | Absensi | 00–02 | sesi, catatan, rekap absensi |
| PRD 04 | Penilaian & Rapor | 00–03 | nilai, aturan kalkulasi, rapor, promosi/kelulusan |
| PRD 05 | Dashboard & Administrasi | 00–04 | dashboard, kalender, pengumuman, surat, notifikasi |
| PRD 06 | Portal Siswa & Orang Tua | 00–05 | portal dengan hubungan akses yang terverifikasi |
| PRD 07 | Modul Tambahan | sesuai domain | PPDB/LMS/finance/library/inventory sesuai prioritas |
| PRD 08 | Production Readiness | fondasi + modul target | backup, monitoring, security, performance, deployment |
| PRD 09 | AI School Assistant | 00–08 | AI provider-agnostic dan tool terotorisasi |

## Definition of Done umum
- Acceptance criteria terpenuhi.
- Migration dan rollback ditinjau.
- Test happy path, validation, authorization, tenant isolation dan edge cases lulus.
- Tidak ada secret atau data pribadi di repository/log.
- UI responsif dan state loading/error/empty tersedia.
- Dokumentasi dan data dictionary diperbarui.
- Hasil test yang dilaporkan benar-benar dijalankan.
- Diff ditinjau sebelum commit.
