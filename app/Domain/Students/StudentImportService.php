<?php

namespace App\Domain\Students;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class StudentImportService
{
    private const REQUIRED_HEADERS = ['nis', 'full_name'];

    private const OPTIONAL_HEADERS = ['nisn', 'birth_date', 'gender', 'status'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {
    }

    /**
     * Parse and validate every row without writing anything.
     *
     * @return array{headers: list<string>, rows: list<array{line: int, data: array<string, string>, errors: list<string>}>}
     */
    public function preview(UploadedFile $file): array
    {
        $rawRows = $this->parse($file);
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rawRows) ?? []);

        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing),
            ]);
        }

        $schoolId = $this->currentSchool->school()?->getKey();
        $existingNis = Student::withTrashed()
            ->where('school_id', $schoolId)
            ->pluck('nis')
            ->all();

        $rows = [];
        $seenNis = [];

        foreach ($rawRows as $index => $rawRow) {
            $line = $index + 2;
            $data = [];
            foreach ($headers as $colIndex => $header) {
                $data[$header] = trim((string) ($rawRow[$colIndex] ?? ''));
            }

            $errors = [];

            if (($data['nis'] ?? '') === '') {
                $errors[] = 'NIS wajib diisi.';
            } else {
                if (in_array($data['nis'], $existingNis, true) || in_array($data['nis'], $seenNis, true)) {
                    $errors[] = 'NIS "'.$data['nis'].'" sudah terdaftar.';
                }
                $seenNis[$data['nis']] = true;
            }

            if (($data['full_name'] ?? '') === '') {
                $errors[] = 'Nama lengkap wajib diisi.';
            }

            if (($data['birth_date'] ?? '') !== '' && ! strtotime($data['birth_date'])) {
                $errors[] = 'Tanggal lahir tidak valid.';
            }

            if (($data['gender'] ?? '') !== '' && Gender::tryFrom($data['gender']) === null) {
                $errors[] = 'Gender harus male/female.';
            }

            if (($data['status'] ?? '') !== '' && StudentStatus::tryFrom($data['status']) === null) {
                $errors[] = 'Status tidak dikenal.';
            }

            $rows[] = ['line' => $line, 'data' => $data, 'errors' => $errors];
        }

        return ['headers' => array_values($headers), 'rows' => $rows];
    }

    /**
     * Write all previewed rows in one transaction; refuses partial writes.
     *
     * @param  list<array{line: int, data: array<string, string>, errors: list<string>}>  $rows
     * @return int number of students created
     */
    public function commit(array $rows, User $actor): int
    {
        $hasErrors = collect($rows)->contains(fn (array $row): bool => $row['errors'] !== []);

        if ($hasErrors || $rows === []) {
            throw ValidationException::withMessages([
                'file' => 'Terdapat baris error; perbaiki file lalu unggah ulang. Tidak ada data yang ditulis.',
            ]);
        }

        $created = 0;

        DB::transaction(function () use ($rows, $actor, &$created): void {
            foreach ($rows as $row) {
                $student = new Student([
                    'nis' => $row['data']['nis'],
                    'nisn' => $row['data']['nisn'] ?? null ?: null,
                    'full_name' => $row['data']['full_name'],
                    'birth_date' => $row['data']['birth_date'] ?? null ?: null,
                    'gender' => $row['data']['gender'] ?? null ?: null,
                    'status' => $row['data']['status'] !== '' ? $row['data']['status'] : StudentStatus::Active->value,
                ]);
                $student->assignToCurrentSchool();
                $student->save();
                $created++;
            }

            $this->audit->record(
                action: 'students.imported',
                school: $this->currentSchool->school(),
                actor: $actor,
                metadata: ['count' => $created],
            );
        });

        return $created;
    }

    /**
     * @return list<list<string>>
     */
    private function parse(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $isXlsx = str_ends_with(strtolower($file->getClientOriginalExtension()), 'xlsx');

        $reader = $isXlsx ? new XlsxReader : new CsvReader;
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map('strval', $row->getCells());
            }
            break; // hanya sheet pertama
        }

        $reader->close();

        return $rows;
    }
}
