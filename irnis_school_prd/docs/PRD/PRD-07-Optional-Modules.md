# PRD 07 — Modul Tambahan: PPDB, LMS, Keuangan & Lainnya

## Sub-Modul Diprioritaskan: Modul 7A — LMS: Kuis

> Fokus iterasi 1 (PRD mini): **Kuis** (pertanyaan + pilihan + skor). Materi, tugas, ujian, dan pengumpulan lanjut di iterasi berikutnya.

### Tujuan
Memberi siswa mata pelajaran berbasis kuis yang bisa jawab di portal/SPP, dengan skor tampung; guru mengelola soal di dalam sekolah.

### Status saat ini
- **DONE** (implementasi iterasi 1):
  - Migration `create_7a_quiz_questions_table` + model `QuizQuestion`
  - Factory `QuizQuestionFactory`
  - Policy `QuizQuestionPolicy` + permission `7a.quiz_questions`
  - `QuizQuestionService` (buat/mutasi nilainilai soal; validasi tenant)
  - `QuizQuestionsController` + view `resources/views/quiz-questions/` (index/detail)
  - Route 7a (`quiz-questions`, `quiz-questions/{quiz}/edit`, `quiz-questions/{quiz}/destroy`)
  - Seeder `QuizQuestionSeeder` (soal contoh)
  - `tests/Feature/QuizQuestionTest` (6 test: daftar, detail, ubah, cabut, tenant-isolation, role-only)
- **CATATAN** + `ROADMAP.md` diperbarui.
- **Pending**: kuis utuh (soal tersentralisasi per kuis), inkremental izinnya sendiri, integrasi `KuisSubmission` (PRD-07 iterasi 2).

### Status saat ini
- **DONE** (implementasi iterasi 1):
  - Migration `create_7a_quiz_questions_table` + model `QuizQuestion`
  - Factory `QuizQuestionFactory`
  - Policy `QuizQuestionPolicy` + permission `7a.quiz_questions`
  - `QuizQuestionService` (buat/mutasi nilainilai soal; validasi tenant)
  - `QuizQuestionsController` + view `resources/views/quiz-questions/` (index/detail)
  - Route 7a (`quiz-questions`, `quiz-questions/{quiz}/edit`, `quiz-questions/{quiz}/destroy`)
  - Seeder `QuizQuestionSeeder` (soal contoh)
  - `tests/Feature/QuizQuestionTest` (6 test: daftar, detail, ubah, cabut, tenant-isolation, role-only)
- **CATATAN** + `ROADMAP.md` diperbarui.
- **Pending**: kuis utuh (soal tersentralisasi per kuis), inkremental izinnya sendiri, integrasi `KuisSubmission` (PRD-07 iterasi 2).
