<?php

namespace Database\Factories;

use App\Enums\ExcuseStatus;
use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceExcuse>
 */
class AttendanceExcuseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'student_id' => null,
            'reason' => fake()->sentence(),
            'document_path' => null,
            'mime_type' => null,
            'size_bytes' => null,
            'status' => ExcuseStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }

    public function forRecord(AttendanceRecord $record): static
    {
        return $this->state(fn (): array => [
            'school_id' => $record->school_id,
            'attendance_record_id' => $record->getKey(),
            'student_id' => $record->student_id,
        ]);
    }

    public function withDocument(string $path, string $mime = 'application/pdf', int $size = 1024): static
    {
        return $this->state(fn (): array => [
            'document_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => $size,
        ]);
    }

    public function reviewed(User $reviewer, ExcuseStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
        ]);
    }
}
