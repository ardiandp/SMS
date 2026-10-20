<?php

namespace App\Models\Domain\Academics;

use App\Domain\Tenancy\ScopedBySchool;
use Database\Factories\Domain\Academics\QuizSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSubmission extends Model
{
    /** @use HasFactory<QuizSubmissionFactory> */
    use HasFactory, ScopedBySchool;

    protected $table = '7a_quiz_submissions';

    protected $fillable = [
        'school_id',
        'quiz_id',
        'student_id',
        'answers',
        'score',
        'max_score',
        'status',
        'submitted_at',
        'graded_by',
        'graded_at',
        'feedback',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'integer',
        'max_score' => 'integer',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'feedback' => 'string',
        'status' => 'string',
    ];

    /** @return BelongsTo<\App\Models\Domain\Academics\Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Domain\Academics\Quiz::class, 'quiz_id');
    }

    /** @return BelongsTo<\App\Models\Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Student::class, 'student_id');
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'graded_by');
    }
}
