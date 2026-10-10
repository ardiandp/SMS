<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_session_id' => AttendanceSession::factory(),
            'student_id' => Student::factory(),
            'status' => AttendanceStatus::Present,
            'notes' => null,
            'marked_by' => null,
            'marked_at' => now(),
            'previous_status' => null,
            'corrected_by' => null,
            'corrected_at' => null,
            'correction_count' => 0,
        ];
    }

    /**
     * @param  array<string, AttendanceStatus>  $statusesByStudent  keyed by student id
     */
    public function forSession(AttendanceSession $session, User $marker, array $statusesByStudent = []): static
    {
        return $this->state(function () use ($session, $marker, $statusesByStudent): array {
            $studentId = array_key_first($statusesByStudent) ?? null;

            return [
                'school_id' => $session->school_id,
                'attendance_session_id' => $session->getKey(),
                'student_id' => $studentId ?? Student::factory(),
                'status' => $studentId !== null
                    ? $statusesByStudent[$studentId]
                    : AttendanceStatus::Present,
                'marked_by' => $marker->getKey(),
            ];
        });
    }

    public function withStatus(AttendanceStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function corrected(AttendanceStatus $previous, User $actor): static
    {
        return $this->state(fn (): array => [
            'previous_status' => $previous,
            'corrected_by' => $actor->getKey(),
            'corrected_at' => now(),
            'correction_count' => 1,
        ]);
    }
}
