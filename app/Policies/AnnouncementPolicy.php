<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\Announcement;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

/**
 * Pengumuman dibaca semua warga sekolah; kelola & terbit hanya untuk
 * pemegang manage.announcements (PRD-05). Guru/staf membaca lewat dasbor
 * tanpa permission tambahan selain view.dashboard.
 */
class AnnouncementPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.dashboard');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->owns($announcement) && $user->can('view.dashboard');
    }

    public function create(User $user): bool
    {
        return $this->inSchool() && $user->can('manage.announcements');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->owns($announcement) && $user->can('manage.announcements');
    }

    public function publish(User $user, Announcement $announcement): bool
    {
        return $this->owns($announcement) && $user->can('manage.announcements');
    }

    public function archive(User $user, Announcement $announcement): bool
    {
        return $this->owns($announcement) && $user->can('manage.announcements');
    }
}
