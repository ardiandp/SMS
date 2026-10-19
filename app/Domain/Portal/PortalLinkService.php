<?php

namespace App\Domain\Portal;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\Guardian;
use App\Models\GuardianUserLink;
use App\Models\Student;
use App\Models\StudentUserLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menghubungkan / mencabut akun portal ke profil siswa atau wali (PRD-06).
 *
 * Aturan yang ditegakkan di server pada setiap pemanggilan:
 * - profil (siswa/wali) dan akun harus berada di sekolah aktif yang sama —
 *   SchoolScope membuat profil tenant lain resolve ke null → 404 aman,
 *   sehingga link lintas tenant tidak mungkin terbentuk;
 * - satu akun hanya boleh menempel ke satu profil per tenant (UNIQUE
 *   school+user di kedua tabel link); duplikat jadi pesan validasi;
 * - setiap link dan revoke dicatat di audit log (account revocation test).
 */
class PortalLinkService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    public function linkStudent(Student $student, User $account, User $actor): StudentUserLink
    {
        return DB::transaction(function () use ($student, $account, $actor): StudentUserLink {
            $this->assertSameSchool($account);
            $this->assertAccountFree($account);

            $link = new StudentUserLink([
                'student_id' => $student->getKey(),
                'user_id' => $account->getKey(),
            ]);
            $link->assignToCurrentSchool();
            $link->save();

            $this->audit->record(
                action: 'portal.student_linked',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $student,
                metadata: [
                    'student_id' => $student->getKey(),
                    'user_id' => $account->getKey(),
                ],
            );

            return $link;
        });
    }

    public function linkGuardian(Guardian $guardian, User $account, User $actor): GuardianUserLink
    {
        return DB::transaction(function () use ($guardian, $account, $actor): GuardianUserLink {
            $this->assertSameSchool($account);
            $this->assertAccountFree($account);

            $link = new GuardianUserLink([
                'guardian_id' => $guardian->getKey(),
                'user_id' => $account->getKey(),
            ]);
            $link->assignToCurrentSchool();
            $link->save();

            $this->audit->record(
                action: 'portal.guardian_linked',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $guardian,
                metadata: [
                    'guardian_id' => $guardian->getKey(),
                    'user_id' => $account->getKey(),
                ],
            );

            return $link;
        });
    }

    /**
     * Cabut akses portal akun ini di sekolah aktif. Baris link dihapus —
     * akun tetap ada (bisa di-link ulang), tetapi PortalAccess langsung
     * berhenti menampilkannya (account revocation).
     */
    public function revoke(User $account, User $actor): void
    {
        DB::transaction(function () use ($account, $actor): void {
            $school = $this->currentSchool->school();

            $studentLinks = StudentUserLink::query()->where('user_id', $account->getKey())->get();
            $guardianLinks = GuardianUserLink::query()->where('user_id', $account->getKey())->get();

            foreach ($studentLinks as $link) {
                $link->delete();
            }

            foreach ($guardianLinks as $link) {
                $link->delete();
            }

            $this->audit->record(
                action: 'portal.access_revoked',
                school: $school,
                actor: $actor,
                subject: $account,
                metadata: [
                    'user_id' => $account->getKey(),
                    'student_links_removed' => $studentLinks->count(),
                    'guardian_links_removed' => $guardianLinks->count(),
                ],
            );
        });
    }

    /**
     * Akun harus anggota aktif sekolah ini — mencegah link ke akun tenant
     * lain atau akun yang sudah dinonaktifkan.
     */
    private function assertSameSchool(User $account): void
    {
        $schoolId = $this->currentSchool->school()?->getKey();

        $member = $schoolId !== null && $account->memberships()
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->exists();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun bukan anggota aktif sekolah ini.',
            ]);
        }
    }

    /**
     * Satu akun, satu profil per tenant — di kedua tabel sekaligus, karena
     * UNIQUE dijaga per tabel tetapi akun ganda-peran (siswa sekaligus wali)
     * tidak didukung portal.
     */
    private function assertAccountFree(User $account): void
    {
        $taken = StudentUserLink::query()->where('user_id', $account->getKey())->exists()
            || GuardianUserLink::query()->where('user_id', $account->getKey())->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun ini sudah terhubung ke profil lain di sekolah ini.',
            ]);
        }
    }
}
