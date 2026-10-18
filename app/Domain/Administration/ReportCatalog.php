<?php

namespace App\Domain\Administration;

use App\Models\Announcement;
use App\Models\SchoolLetter;
use App\Models\Student;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry laporan administratif generik (PRD-05).
 *
 * Setiap laporan didefinisikan di server sebagai whitelist ketat: model
 * (otomatis ter-scope SchoolScope), permission, kolom yang boleh dipilih
 * beserta penerjemah nilai, dan kolom dasar untuk filter periode. Nama
 * tabel/kolom tidak pernah datang dari input user — hanya kunci yang ada di
 * registry ini yang dikenal, sehingga tidak ada celah injeksi skema.
 */
class ReportCatalog
{
    /**
     * Batas baris ekspor CSV. Laporan lebih besar harus mempersempit periode
     * terlebih dahulu; nilai bisa dioverride lewat config('reports.export_limit')
     * (dipakai test agar tidak perlu membuat ribuan baris).
     */
    public const EXPORT_LIMIT = 5000;

    /**
     * @return array<string, array{
     *   label: string,
     *   model: class-string<Model>,
     *   permission: string,
     *   date_column: string|null,
     *   columns: array<string, array{label: string, value: Closure(Model): mixed}>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'students' => [
                'label' => 'Rekap Siswa',
                'model' => Student::class,
                'permission' => 'view.students',
                'date_column' => 'created_at',
                'columns' => [
                    'nis' => ['label' => 'NIS', 'value' => fn (Student $student) => $student->nis],
                    'nisn' => ['label' => 'NISN', 'value' => fn (Student $student) => $student->nisn],
                    'full_name' => ['label' => 'Nama', 'value' => fn (Student $student) => $student->full_name],
                    'gender' => ['label' => 'Jenis Kelamin', 'value' => fn (Student $student) => $student->gender?->label()],
                    'birth_date' => ['label' => 'Tanggal Lahir', 'value' => fn (Student $student) => $student->birth_date?->toDateString()],
                    'status' => ['label' => 'Status', 'value' => fn (Student $student) => $student->status?->label()],
                ],
            ],
            'letters' => [
                'label' => 'Log Surat Sekolah',
                'model' => SchoolLetter::class,
                'permission' => 'manage.letters',
                'date_column' => 'issued_at',
                'columns' => [
                    'letter_number' => ['label' => 'Nomor Surat', 'value' => fn (SchoolLetter $letter) => $letter->letter_number],
                    'subject' => ['label' => 'Perihal', 'value' => fn (SchoolLetter $letter) => $letter->subject],
                    'status' => ['label' => 'Status', 'value' => fn (SchoolLetter $letter) => $letter->status->label()],
                    'issued_at' => ['label' => 'Tanggal Terbit', 'value' => fn (SchoolLetter $letter) => $letter->issued_at?->toDateString()],
                ],
            ],
            'announcements' => [
                'label' => 'Rekap Pengumuman',
                'model' => Announcement::class,
                'permission' => 'manage.announcements',
                'date_column' => 'published_at',
                'columns' => [
                    'title' => ['label' => 'Judul', 'value' => fn (Announcement $announcement) => $announcement->title],
                    'status' => ['label' => 'Status', 'value' => fn (Announcement $announcement) => $announcement->status->label()],
                    'published_at' => ['label' => 'Tanggal Terbit', 'value' => fn (Announcement $announcement) => $announcement->published_at?->toDateTimeString()],
                ],
            ],
        ];
    }

    public static function has(string $key): bool
    {
        return isset(self::definitions()[$key]);
    }

    /**
     * Definisi yang boleh dilihat user ini — daftar laporan di layar hanya
     * menampilkan laporan berotorisasi; route show/export tetap mengcek ulang.
     *
     * @return array<string, array>
     */
    public static function visibleTo(User $user): array
    {
        return array_filter(
            self::definitions(),
            fn (array $definition) => $user->can($definition['permission']),
        );
    }
}
