<?php

namespace App\Domain\Administration;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\PlacementStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\GuardianUserLink;
use App\Models\SchoolLetter;
use App\Models\StudentUserLink;
use App\Models\StudyGroupMember;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\LetterIssued;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Spatie\Permission\Models\Role;

/**
 * Pengirim notifikasi in-app (PRD-05).
 *
 * Semua penerima dihitung dari konteks sekolah aktif: pengumuman mengikuti
 * audience eksplisitnya ("all", "role"; audience study_group menunjuk siswa
 * yang belum memiliki akun sampai portal PRD-06), sementara penerbitan surat
 * memberi tahu pengelola surat sekolah. Pelaku aksi tidak diberi notifikasi.
 */
class NotificationDispatcher
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
    ) {}

    public function announcePublished(Announcement $announcement, User $actor): void
    {
        $this->send(
            $this->audienceUsers($announcement),
            new AnnouncementPublished($announcement),
            $actor,
        );
    }

    public function letterIssued(SchoolLetter $letter, User $actor): void
    {
        $this->send(
            User::role(['school-admin', 'super-admin'])
                ->where('status', UserStatus::Active->value)
                ->get(),
            new LetterIssued($letter),
            $actor,
        );
    }

    /**
     * Pengguna sekolah aktif sesuai audience eksplisit pengumuman.
     *
     * @return Collection<int, User>
     */
    private function audienceUsers(Announcement $announcement): Collection
    {
        $users = new Collection;

        foreach ($announcement->recipients as $recipient) {
            $users = $users->concat(match ($recipient->audience_type) {
                'all' => $this->schoolMembers(),
                'role' => $this->roleUsers($recipient->audience_id),
                // study_group (PRD-06): akun portal siswa yang penempatannya
                // aktif di rombel itu + akun wali yang terhubung ke siswa
                // tersebut dan memilih menerima notifikasi.
                'study_group' => $this->studyGroupUsers($recipient->audience_id),
                default => new Collection,
            });
        }

        return $users->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function schoolMembers(): Collection
    {
        $schoolId = $this->currentSchool->school()?->getKey();

        if ($schoolId === null) {
            return new Collection;
        }

        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereIn('id', function ($query) use ($schoolId): void {
                $query->select('user_id')
                    ->from('school_user_memberships')
                    ->where('school_id', $schoolId)
                    ->where('status', 'active');
            })
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function roleUsers(?int $roleId): Collection
    {
        if ($roleId === null) {
            return new Collection;
        }

        $role = Role::query()->find($roleId);

        if ($role === null) {
            return new Collection;
        }

        // Role dipakai lewat fitur teams Spatie: hanya anggota role di
        // sekolah aktif (team id dari CurrentSchool) yang kembali.
        return User::role($role->name)
            ->where('status', UserStatus::Active->value)
            ->get();
    }

    /**
     * Akun portal untuk satu rombel (PRD-06): akun siswa yang penempatannya
     * aktif di rombel + akun wali dari siswa-siswa itu — tetapi hanya wali
     * yang pivot `receives_notifications`-nya true (preferensi komunikasi).
     * Wali yang opt-out tetap bisa membaca pengumuman lewat portal, hanya
     * tidak menerima notifikasi push.
     *
     * @return Collection<int, User>
     */
    private function studyGroupUsers(?int $studyGroupId): Collection
    {
        if ($studyGroupId === null) {
            return new Collection;
        }

        $studentIds = StudyGroupMember::query()
            ->where('study_group_id', $studyGroupId)
            ->where('status', PlacementStatus::Active)
            ->pluck('student_id')
            ->all();

        if ($studentIds === []) {
            return new Collection;
        }

        $studentUserIds = StudentUserLink::query()
            ->whereIn('student_id', $studentIds)
            ->pluck('user_id')
            ->all();

        // Wali: hanya yang masih terhubung (link aktif) ke siswa rombel ini
        // DAN memilih menerima notifikasi pada pivot student_guardians.
        $guardianIds = \App\Models\StudentGuardian::query()
            ->whereIn('student_id', $studentIds)
            ->where('receives_notifications', true)
            ->pluck('guardian_id')
            ->all();

        $guardianUserIds = $guardianIds === []
            ? []
            : GuardianUserLink::query()
                ->whereIn('guardian_id', $guardianIds)
                ->pluck('user_id')
                ->all();

        $ids = array_unique([...$studentUserIds, ...$guardianUserIds]);

        if ($ids === []) {
            return new Collection;
        }

        return User::query()
            ->whereIn('id', $ids)
            ->where('status', UserStatus::Active->value)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Collection $recipients, Notification $notification, User $actor): void
    {
        if ($this->currentSchool->school() === null) {
            return;
        }

        foreach ($recipients as $user) {
            if ((int) $user->getKey() === (int) $actor->getKey()) {
                continue;
            }

            $user->notify($notification);
        }
    }
}
