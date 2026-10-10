<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    use ScopedBySchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'document_type',
        'storage_path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
