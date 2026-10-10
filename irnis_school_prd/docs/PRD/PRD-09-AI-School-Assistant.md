# PRD 09 — AI School Assistant (Provider-Agnostic)

## Tujuan
Menyediakan asisten AI untuk menjawab pertanyaan dan membuat ringkasan dari data sekolah yang diizinkan.

## Scope fase pertama
- Provider interface dan adapter yang dapat diganti tanpa mengubah domain akademik.
- Percakapan, pesan, tool calls, feedback, usage tracking.
- Tool allowlist seperti `getAttendanceSummary`, `getAcademicReport`, `getStudentContext` sesuai role.
- Ringkasan laporan dan jawaban berbasis hasil service Laravel.
- Konfirmasi manusia untuk tindakan berdampak tinggi.
- Kebijakan retensi, redaksi dan pengukuran biaya/latensi.

## Out of scope
- SQL bebas dari model.
- AI mengubah nilai, menerbitkan rapor, memutuskan kenaikan/kelulusan secara otomatis.
- Pelatihan model dengan data siswa tanpa penilaian privasi, legal, dan persetujuan yang sesuai.

## Dependencies
PRD 00–08, terutama tenant isolation, audit, domain services dan role policies.

## Schema
`ai_conversations`, `ai_messages`, `ai_tool_runs`, `ai_usage_logs`, opsional `ai_feedback`.

## Acceptance criteria
- Provider dapat diganti melalui konfigurasi/service binding tanpa mengubah domain akademik.
- Setiap tool memverifikasi user, tenant, permission dan parameter di server.
- Model tidak menerima data lebih banyak daripada yang diperlukan untuk menjawab.
- Tool outputs tervalidasi dan dibatasi; AI tidak menjalankan SQL bebas.
- Semua tool run tercatat dengan ringkasan yang tidak membocorkan data sensitif.
- Prompt injection pada input/dokumen tidak dapat menonaktifkan policy.
- Penggunaan, error, latency dan biaya dapat diukur.
- Pengguna diberi tahu saat jawaban adalah hasil AI; data sumber/periodenya disebutkan bila tersedia.
- Tidak ada tindakan high-impact tanpa persetujuan manusia berwenang.

## Security & privacy
Data minimization; redaction; provider retention policy ditinjau; secret dikelola server-side; tool allowlist; output validation; rate limits; per-user/per-school usage limits; audit; retention/deletion policy. Jangan kirim data siswa ke provider sebelum kebutuhan dan dasar pemrosesannya disetujui.

## Tests
Tenant isolation at tool layer, unauthorized tool denial, prompt injection, malformed tool arguments, provider timeout/retry, redaction, rate limit, cost/usage accounting, human approval gate.

## Prompt OpenCode
Implementasikan PRD-09 setelah domain services dan policies stabil. Buat interface provider, adapter, tool registry allowlist, dan orchestration service. AI hanya memanggil service Laravel yang terotorisasi; dilarang menjalankan SQL bebas. Mulai dari read-only attendance summary dan report summary. Tambahkan tests untuk akses lintas tenant, prompt injection, provider failure, redaction dan approval gate. Jangan mengirim data pribadi nyata ke provider selama development.
