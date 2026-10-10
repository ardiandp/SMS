# Architecture — IRNIS School Management Platform

## 1. Product scope from proposal
Proposal IRNIS School Digitalization menampilkan modul:
- Data siswa dan orang tua/wali.
- Data guru dan staf serta manajemen pengguna.
- Tahun ajaran, kelas/rombel, mata pelajaran, jadwal pelajaran.
- Absensi siswa, nilai siswa, rapor, kenaikan kelas, kelulusan.
- Pengumuman sekolah, kalender akademik, surat sekolah, dashboard.
- Akses web dan mobile/portal untuk administrator, kepala sekolah, guru, staf, siswa, dan orang tua.
- Security & Data Protection: role, hak akses, backup, audit aktivitas, proteksi data dan session management.
- AI School Assistant: analisis data, akademik, absensi, laporan, administrasi, rekomendasi.
- Modul tambahan yang tercantum pada proposal: PPDB, LMS/e-learning, school finance, payment gateway, parent portal, student portal, library, inventory, health/UKS, extracurricular, alumni, HR/payroll, dan modul lainnya.
- Kustomisasi branding, workflow, dan modul.

Cakupan persis dan prioritas harus dikonfirmasi dengan sekolah; gambar proposal bukan spesifikasi rinci aturan bisnis.

## 2. Architecture decisions
- **Deployment:** SaaS, satu aplikasi dan database bersama pada fase awal.
- **Tenant model:** `schools` adalah tenant; tabel domain sekolah memiliki `school_id`.
- **Jenjang:** configurable; jangan hard-code hanya SD/SMP/SMA. Simpan jenjang sebagai data referensi atau konfigurasi sekolah.
- **Application:** Laravel modular monolith.
- **UI:** Blade, Livewire, Tailwind.
- **Database:** MySQL 8.4 LTS, InnoDB, utf8mb4.
- **AI:** provider interface + adapters; AI dikerjakan setelah alur inti stabil.
- **Files:** private storage untuk dokumen siswa/rapor; akses via controller/policy atau temporary signed URL dengan masa berlaku singkat.
- **Async work:** Laravel queue; mulai dari database queue bila skala kecil, pindah ke Redis jika perlu.
- **Audit:** append-oriented audit events untuk aksi sensitif; jangan merekam payload sensitif lengkap.
- **Observability:** structured logs dengan redaction, health checks, metrics sesuai kebutuhan.

## 3. Domain boundaries
1. Platform & Tenancy: schools, memberships, users, roles, permissions, audit.
2. Identity: student, guardian, employee profiles.
3. Academic Structure: academic years, semesters, grade levels, classrooms, study groups, subjects, teaching assignments, schedules.
4. Attendance: sessions, records, excuses.
5. Assessment & Reporting: assessment definitions, scores, grading policies, final grades, report cards, promotion/graduation.
6. Communication & Administration: events, announcements, letters, notifications.
7. Admissions (PPDB): applications, decisions, enrollment.
8. LMS: courses, materials, assignments, submissions, quizzes (scope detail later).
9. Finance: invoices, invoice items, payments, payment allocations, refunds (payment provider integration only after reconciliation rules defined).
10. AI: conversations, messages, tool runs, feedback, usage logs; separated from core academic services.

## 4. Tenant security model
- Resolve active tenant from authenticated user's validated membership/subdomain or explicit tenant switch that validates membership.
- Never trust `school_id` sent from browser as authority.
- Every tenant-scoped service/query applies tenant scope; Policies verify both membership and role permission.
- Global platform operators must use explicit audited support access, least privilege, and preferably time-bound access.
- Cross-tenant foreign-key integrity needs special care: a normal FK to `id` alone does not ensure same-school ownership. Use composite unique keys/FKs where practical, or enforce invariant through domain services and tests. Document any exceptions.
- Add negative tests: user in school A cannot view/update/delete school B's student, grades, attendance, files, invoices, or AI conversation.

## 5. Suggested folder boundaries
Use conventional Laravel folders first. Add domain folders only when they improve separation:
- `app/Domain/Tenancy`
- `app/Domain/Students`
- `app/Domain/Staff`
- `app/Domain/Academics`
- `app/Domain/Attendance`
- `app/Domain/Assessment`
- `app/Domain/Administration`
- `app/Domain/Admissions`
- `app/Domain/Lms`
- `app/Domain/Finance`
- `app/Domain/Ai`
Each domain can contain Actions/Services, DTOs where useful, policies, and tests. Do not create abstractions without a current use case.

## 6. Non-functional requirements
- Security: authorization server-side, least privilege, tenant isolation, safe file handling, secrets management.
- Reliability: transaction boundaries, backup/restore drills, idempotent queued jobs where applicable.
- Privacy: data minimization, retention policy, controlled export, access audit.
- Performance: pagination, indexes driven by query patterns, eager loading, avoid N+1.
- Maintainability: documented schema, migration discipline, automated tests, code review.
- Accessibility/usability: responsive layouts and clear validation feedback for school staff.
- Localization: Indonesian UI first; store timestamps consistently and display using configured school timezone.
