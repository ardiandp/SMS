<?php

namespace App\Models\Domain\Academics;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\Domain\Academics\QuizFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory, ScopedBySchool;

    protected $table = '7a_quizzes';

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'subject_id',
        'study_group_id',
        'title',
        'description',
        'time_limit',
        'shuffle_questions',
        'shuffle_choices',
        'passing_score',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'time_limit' => 'integer',
        'shuffle_questions' => 'boolean',
        'shuffle_choices' => 'boolean',
        'passing_score' => 'integer',
        'is_active' => 'boolean',
    ];

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

    /** @return HasMany<\App\Models\Domain\Academics\QuizQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(\App\Models\Domain\Academics\QuizQuestion::class, 'quiz_id')
            ->orderBy('created_at');
    }
}
