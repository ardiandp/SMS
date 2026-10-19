<?php

namespace App\Domain\Portal;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\GuardianUserLink;
use App\Models\Student;
use App\Models\StudentUserLink;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolver akses portal siswa & orang tua (PRD-06).
 *
 * Satu-satunya sumber kebenaran "siswa mana boleh dilihat akun ini":
 * - akun role student → dirinya sendiri via student_user_links;
 * - akun role parent → anak-anak wali via guardian_user_links + student_guardians.
 *
 * Semua query di-scope sekolah aktif (SchoolScope pada model link), sehingga
 * baris link tenant lain tidak pernah terbaca. Method ini mengembalikan
 * ID integer biasa — ID hanyalah hasil otorisasi, bukan penggantinya:
 * controller wajib memanggil allowStudent() sebelum menampilkan data apa pun.
 */
class PortalAccess
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * Siswa yang boleh dilihat akun ini di sekolah aktif.
     *
     * @return Collection<int, Student>
     */
    public function visibleStudents(User $user): Collection
    {
        $ids = $this->visibleStudentIds($user);

        if ($ids === []) {
            return new Collection();
        }

        return Student::query()->whereKey($ids)->orderBy('full_name')->get();
    }

    /**
     * @return list<int>
     */
    public function visibleStudentIds(User $user): array
    {
        $userId = $user->getKey();

        $own = StudentUserLink::query()
            ->where('user_id', $userId)
            ->pluck('student_id')
            ->all();

        $guardianIds = GuardianUserLink::query()
            ->where('user_id', $userId)
            ->pluck('guardian_id')
            ->all();

        $children = $guardianIds === []
            ? []
            : Student::query()
                ->whereHas('guardians', fn ($q) => $q->whereIn('guardians.id', $guardianIds))
                ->pluck('students.id')
                ->all();

        $ids = array_unique(array_map('intval', [...$own, ...$children]));
        sort($ids);

        return $ids;
    }

    /**
     * Apakah akun boleh melihat siswa ini. Satu-satunya gate sebelum data
     * siswa apa pun ditampilkan — mencegah IDOR (menebak ID siswa lain).
     */
    public function canSeeStudent(User $user, int $studentId): bool
    {
        return in_array($studentId, $this->visibleStudentIds($user), true);
    }

    /**
     * Abort 403 bila tidak boleh. Dipanggil controller di awal setiap aksi
     * portal yang menerima student id dari user.
     */
    public function allowStudent(User $user, int $studentId): void
    {
        abort_unless($this->canSeeStudent($user, $studentId), 403);
    }

    /**
     * Sekolah aktif tempat link portal dibaca. Link selalu dibaca dalam
     * konteks tenant ini — SchoolScope pada model link menegakkannya.
     */
    public function schoolId(): ?int
    {
        return $this->currentSchool->school()?->getKey();
    }
}
