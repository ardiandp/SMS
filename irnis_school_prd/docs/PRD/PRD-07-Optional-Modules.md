# PRD 07 — Modul Tambahan: PPDB, LMS, Keuangan & Lainnya

## Tujuan
Menambah modul bisnis sesuai prioritas sekolah tanpa mencampur domain inti.

## Candidate modules from proposal
- PPDB/penerimaan siswa baru.
- LMS/e-learning: materi, tugas, kuis, ujian, pengumpulan tugas dan progres.
- School Finance/SPP: tagihan, item tagihan, pembayaran, alokasi pembayaran, refund, laporan.
- Perpustakaan, inventaris, UKS/kesehatan, ekstrakurikuler, alumni, HR/payroll.
- Integrasi payment gateway hanya setelah aturan rekonsiliasi dan penanganan callback ditetapkan.

## Dependencies
PRD 00; tiap modul memakai domain terkait dari PRD 01–06.

## Acceptance criteria — umum
- Setiap modul memiliki PRD mini, ERD, data dictionary, role matrix dan definisi status.
- Data tenant-scoped dan policy server-side.
- Workflow memiliki status yang eksplisit dan audit pada aksi finansial/administratif penting.
- Modul dapat diuji terpisah dan tidak merusak domain inti.
- Ekspor dan laporan mematuhi batas tenant dan izin.

## Finance-specific safeguards
- Gunakan decimal untuk nominal uang, bukan floating point.
- Pisahkan invoice, invoice items, payment, payment allocations, refunds, dan payment provider events bila diperlukan.
- Jangan menganggap callback payment gateway sebagai benar sebelum signature/verification diverifikasi.
- Idempotency key untuk event pembayaran dan rekonsiliasi.
- Jangan menghapus transaksi finansial; gunakan reversal/refund yang tercatat.

## Prompt OpenCode
Jangan membangun semua modul tambahan sekaligus. Tanyakan modul mana yang diprioritaskan. Untuk modul terpilih, buat PRD mini, state machine, ERD, data dictionary, permission matrix dan tests terlebih dahulu. Implementasikan satu modul per iterasi. Untuk finance/payment, jangan membuat integrasi gateway sebelum mekanisme verifikasi, idempotency dan rekonsiliasi ditentukan.
