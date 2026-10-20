<?php

namespace App\Models\Domain\Academics;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\Domain\Academics\QuizQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    /** @use HasFactory<QuizQuestionFactory> */
    use HasFactory, ScopedBySchool;

    protected $table = '7a_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'school_id',
        'academic_year_id',
        'subject_id',
        'study_group_id',
        'question_text',
        'choices',
        'correct_choice_index',
        'difficulty',
        'question_type',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'choices' => 'array',
            'correct_choice_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<\App\Models\AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AcademicYear::class);
    }

    /** @return BelongsTo<\App\Models\Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Subject::class);
    }

    /** @return BelongsTo<\App\Models\StudyGroup, $this> */
    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(\App\Models\StudyGroup::class);
    }

    /** @return BelongsTo<\App\Models\Domain\Academics\Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Domain\Academics\Quiz::class, 'quiz_id');
    }
}
