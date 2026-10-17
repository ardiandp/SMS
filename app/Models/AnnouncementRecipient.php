<?php

namespace App\Models;

use App\Domain\Tenancy\ScopedBySchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris audience eksplisit untuk sebuah pengumuman (PRD-05).
 *
 * audience_type yang dikenal: "all" (seluruh sekolah, audience_id null),
 * "role" (audience_id = id role, mis. guru), "study_group" (audience_id =
 * id rombel). Nilai lain ditolak service agar definisi audience selalu
 * eksplisit dan dapat diuji visibilitasnya.
 */
class AnnouncementRecipient extends Model
{
    use ScopedBySchool;

    protected $fillable = [
        'school_id',
        'announcement_id',
        'audience_type',
        'audience_id',
    ];

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
