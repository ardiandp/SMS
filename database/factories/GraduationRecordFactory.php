<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\GraduationRecord;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GraduationRecord>
 */
class GraduationRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'status' => 'pending',
            'document_number' => null,
            'issued_at' => null,
            'issued_by' => null,
        ];
    }
}
