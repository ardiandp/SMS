# Data Dictionary — inti

**Konvensi umum**
- PK: primary key.
- FK: foreign key.
- `id`: `BIGINT UNSIGNED` auto-increment kecuali keputusan arsitektur memilih ULID.
- `school_id`: FK ke `schools.id` pada tabel tenant-scoped.
- timestamps: `created_at`, `updated_at`; gunakan `deleted_at` hanya bila soft delete sesuai kebijakan.
- Semua tabel menggunakan InnoDB dan `utf8mb4`.
- Tabel sensitif perlu kebijakan retensi, akses, dan audit.

## Platform & identity

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| schools | id, name, slug, status, timezone, settings_json nullable, created_at, updated_at | UNIQUE(slug), INDEX(status) | Tenant sekolah; settings JSON hanya untuk konfigurasi non-relasional |
| users | id, name, email, password, email_verified_at, status, last_login_at | UNIQUE(email) atau aturan identitas yang diputuskan | Identitas login global; password di-hash oleh Laravel |
| school_user_memberships | id, school_id, user_id, status, joined_at | UNIQUE(school_id,user_id), INDEX(user_id,status), FK sekolah/user | Menentukan tenant yang boleh diakses user |
| roles/permissions | package schema | UNIQUE nama/guard sesuai package | Gunakan package permission yang konsisten |
| audit_logs | id, school_id nullable, actor_user_id nullable, action, subject_type, subject_id, metadata_json, ip_hash/metadata terbatas, created_at | INDEX(school_id,created_at), INDEX(actor_user_id,created_at), INDEX(subject_type,subject_id) | Append-oriented; hindari menyimpan data pribadi penuh |

## Siswa & keluarga

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| students | id, school_id, nis, nisn nullable, full_name, birth_date nullable, gender nullable, status, created_at, updated_at, deleted_at nullable | UNIQUE(school_id,nis), INDEX(school_id,status), INDEX(school_id,full_name) | NISN dapat punya aturan unik lintas sekolah yang harus dikonfirmasi |
| guardians | id, school_id, full_name, phone nullable, email nullable, address nullable | INDEX(school_id,full_name), INDEX(school_id,phone) | Minimalkan data yang dikumpulkan |
| student_guardians | id, school_id, student_id, guardian_id, relationship, is_primary, can_pickup nullable, receives_notifications | UNIQUE(student_id,guardian_id), INDEX(school_id,guardian_id) | Relasi many-to-many; validasi bahwa siswa/wali berasal dari tenant yang sama |
| student_documents | id, school_id, student_id, document_type, storage_path, mime_type, size_bytes, uploaded_by, created_at | INDEX(school_id,student_id), FK ke siswa/pengunggah | File private; jangan simpan isi file di DB |

## Guru/staf

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| employees | id, school_id, employee_number, full_name, employment_status, joined_on nullable, left_on nullable | UNIQUE(school_id,employee_number), INDEX(school_id,employment_status) | Identitas pegawai; tidak semua pegawai adalah guru |
| employee_user_links | id, school_id, employee_id, user_id | UNIQUE(school_id,employee_id), UNIQUE(school_id,user_id) sesuai kebijakan | Pengaitan akun login dan pegawai |
| employee_assignments | id, school_id, employee_id, role_title, starts_on, ends_on nullable | INDEX(school_id,employee_id,starts_on) | Riwayat penugasan |

## Struktur akademik

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| academic_years | id, school_id, name, starts_on, ends_on, is_active | UNIQUE(school_id,name), CHECK(starts_on <= ends_on), INDEX(school_id,is_active) | Validasi satu tahun aktif dilakukan di service/transaksi atau desain constraint yang sesuai |
| semesters | id, school_id, academic_year_id, name, starts_on, ends_on, sequence | UNIQUE(academic_year_id,sequence), CHECK(starts_on <= ends_on) | Pastikan tenant semester sesuai tenant tahun ajaran |
| grade_levels | id, school_id, code, name, education_level, sequence, is_active | UNIQUE(school_id,code), INDEX(school_id,education_level) | Konfigurasi multi-jenjang; bukan enum hard-coded |
| classrooms | id, school_id, name, building nullable, capacity nullable, status | UNIQUE(school_id,name), INDEX(school_id,status) | Ruang fisik |
| study_groups | id, school_id, academic_year_id, grade_level_id, classroom_id nullable, name, homeroom_employee_id nullable, capacity nullable, status | UNIQUE(academic_year_id,name), INDEX(school_id,academic_year_id,grade_level_id) | Rombel per tahun ajaran |
| study_group_members | id, school_id, study_group_id, student_id, starts_on, ends_on nullable, status | INDEX(school_id,study_group_id,status), INDEX(school_id,student_id,starts_on) | Aturan satu penempatan aktif per siswa perlu divalidasi transaksional |
| subjects | id, school_id, code, name, category nullable, is_active | UNIQUE(school_id,code), INDEX(school_id,is_active) | Mata pelajaran |
| teaching_assignments | id, school_id, study_group_id, subject_id, employee_id, semester_id nullable, status | INDEX(school_id,study_group_id), INDEX(school_id,employee_id), UNIQUE sesuai aturan assignment | Pengampu mata pelajaran |
| class_schedules | id, school_id, teaching_assignment_id, day_of_week, starts_at, ends_at, effective_from nullable, effective_until nullable, room_label nullable | INDEX(school_id,day_of_week), INDEX(teaching_assignment_id) | Konflik guru/ruang/waktu perlu validasi aplikasi |

## Absensi

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| attendance_sessions | id, school_id, study_group_id, session_date, session_type, teaching_assignment_id nullable, status, created_by | INDEX(school_id,session_date), UNIQUE sesuai tipe sesi/rombel/jadwal | Tentukan apakah absensi harian atau per jam pelajaran |
| attendance_records | id, school_id, attendance_session_id, student_id, status, notes nullable, marked_by, marked_at | UNIQUE(attendance_session_id,student_id), INDEX(school_id,student_id,marked_at) | Status dibatasi nilai yang disepakati |
| attendance_excuses | id, school_id, attendance_record_id, reason, document_path nullable, reviewed_by nullable, reviewed_at nullable | UNIQUE(attendance_record_id) bila satu pengajuan per record | File bukti private |

## Nilai & rapor

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| assessment_categories | id, school_id, name, default_weight nullable, is_active | UNIQUE(school_id,name) bila nama global di tenant | Bobot final dapat berbeda per kebijakan/kelas |
| assessments | id, school_id, teaching_assignment_id, semester_id, category_id nullable, title, assessment_date, max_score, weight nullable, status | INDEX(school_id,semester_id), INDEX(teaching_assignment_id,assessment_date) | Validasi max_score > 0 dan bobot sesuai aturan |
| assessment_scores | id, school_id, assessment_id, student_id, score nullable, feedback nullable, entered_by, finalized_at nullable | UNIQUE(assessment_id,student_id), INDEX(school_id,student_id) | Simpan nilai mentah; kontrol edit setelah finalisasi |
| grading_policies | id, school_id, academic_year_id, name, config_json, version, status | INDEX(school_id,academic_year_id,status) | Konfigurasi aturan, terversi; hindari mengandalkan JSON untuk relasi |
| final_grades | id, school_id, student_id, teaching_assignment_id, semester_id, numeric_score nullable, grade_label nullable, calculation_version, finalized_at nullable | UNIQUE(student_id,teaching_assignment_id,semester_id), INDEX(school_id,semester_id,student_id) | Simpan snapshot hasil perhitungan |
| report_cards | id, school_id, student_id, academic_year_id, semester_id, status, published_at nullable, published_by nullable, revision_no | INDEX(school_id,academic_year_id,semester_id,status), UNIQUE sesuai aturan revisi | Terbitkan snapshot; jangan menimpa hasil lama tanpa audit |
| report_card_items | id, school_id, report_card_id, subject_id, final_grade_snapshot_json, teacher_note nullable | UNIQUE(report_card_id,subject_id) | Snapshot untuk menjaga histori rapor |
| promotion_decisions | id, school_id, student_id, academic_year_id, from_grade_level_id, to_grade_level_id nullable, decision, decided_by, decided_at | INDEX(school_id,academic_year_id,decision) | Keputusan resmi dan audit |
| graduation_records | id, school_id, student_id, academic_year_id, status, document_number nullable, issued_at nullable | UNIQUE(student_id,academic_year_id) | Aturan kelulusan perlu dikonfirmasi |

## Administrasi

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| academic_events | id, school_id, academic_year_id nullable, title, starts_at, ends_at nullable, category, audience_json nullable | INDEX(school_id,starts_at) | Kalender akademik |
| announcements | id, school_id, title, body, status, published_at, created_by | INDEX(school_id,status,published_at) | Isi pengumuman harus disanitasi |
| announcement_recipients | id, school_id, announcement_id, audience_type, audience_id nullable | INDEX(school_id,announcement_id) | Definisi audience harus eksplisit |
| letter_templates | id, school_id, code, name, body_template, version, is_active | UNIQUE(school_id,code) | Template terversi |
| school_letters | id, school_id, template_id nullable, letter_number, subject, body_snapshot, status, issued_at nullable, created_by | UNIQUE(school_id,letter_number), INDEX(school_id,status,created_at) | Snapshot surat yang diterbitkan |
| notifications | id, school_id nullable, user_id, type, data_json, read_at nullable | INDEX(user_id,read_at), INDEX(school_id,created_at) | Jangan memasukkan data sensitif ke payload notifikasi |

## AI (tahap akhir)

| Table | Kolom inti | Constraint/index penting | Catatan |
|---|---|---|---|
| ai_conversations | id, school_id, user_id, title nullable, provider_key nullable, status, created_at | INDEX(school_id,user_id,created_at) | Akses percakapan tenant-scoped |
| ai_messages | id, conversation_id, role, content_redacted, metadata_json nullable, created_at | INDEX(conversation_id,created_at) | Kebijakan retensi dan redaksi |
| ai_tool_runs | id, conversation_id, tool_name, status, input_summary_json, result_summary_json nullable, duration_ms nullable, created_at | INDEX(conversation_id,created_at), INDEX(tool_name,status) | Simpan ringkasan; hindari dump data siswa |
| ai_usage_logs | id, school_id, user_id, provider_key, model_key, input_tokens nullable, output_tokens nullable, estimated_cost nullable, status, created_at | INDEX(school_id,created_at), INDEX(provider_key,created_at) | Jangan simpan API key |

## Constraint lintas tenant
`school_id` pada tabel anak harus konsisten dengan tenant induknya. FK biasa yang hanya menunjuk `id` tidak otomatis menjamin kedua record berada pada sekolah yang sama. Pilih strategi composite key/FK pada relasi penting, atau enforcement service yang ketat dengan tes lintas tenant. Keputusan ini harus dicatat per relasi, bukan diasumsikan selesai hanya dengan global scope.
