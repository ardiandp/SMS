# PRD 04 — Penilaian, Rapor, Kenaikan Kelas & Kelulusan

## Tujuan
Mengelola nilai dan menerbitkan laporan akademik dengan histori yang dapat diaudit.

## Scope
- Kategori/jenis asesmen dan input nilai.
- Nilai mentah, feedback, status draft/final.
- Kebijakan pembobotan yang terversi dan dapat dikonfigurasi.
- Perhitungan nilai akhir berdasarkan aturan yang disetujui sekolah.
- Finalisasi nilai dan workflow koreksi.
- Snapshot rapor per siswa/semester, publikasi, revisi.
- Keputusan kenaikan kelas dan catatan kelulusan.
- Ekspor PDF/print.

## Dependencies
PRD 00–03.

## Schema
`assessment_categories`, `assessments`, `assessment_scores`, `grading_policies`, `final_grades`, `report_cards`, `report_card_items`, `promotion_decisions`, `graduation_records`.

## Acceptance criteria
- Nilai mentah dan nilai hasil kalkulasi disimpan terpisah.
- Setiap hasil akhir dapat ditelusuri ke versi kebijakan kalkulasi.
- Nilai yang telah difinalisasi tidak dapat diubah tanpa permission dan audit.
- Rapor terbit menyimpan snapshot; revisi tidak menghapus histori penerbitan sebelumnya.
- Aturan naik kelas/lulus dikonfigurasi dan memerlukan persetujuan role berwenang.
- PDF hanya dapat diakses oleh pihak yang berhak.
- Perhitungan nilai memiliki test berbasis contoh yang disetujui sekolah.

## Security
Pembatasan berdasarkan school, assignment dan hubungan siswa; audit perubahan nilai; proteksi dokumen; jangan kirim nilai ke provider eksternal tanpa otorisasi dan dasar penggunaan yang jelas.

## Tests
Weight calculations, null/missing scores, rounding, finalized edit denial, revision history, unauthorized report download, tenant isolation, promotion edge cases.

## Prompt OpenCode
Sebelum implementasi, minta contoh kebijakan penilaian dan rapor yang disetujui sekolah. Jangan mengarang aturan akademik. Implementasikan nilai mentah, versi kebijakan, perhitungan deterministik, workflow finalisasi, snapshot rapor dan audit. Tambahkan unit tests untuk setiap contoh perhitungan. AI tidak boleh menghitung atau mengubah nilai resmi pada tahap ini.
