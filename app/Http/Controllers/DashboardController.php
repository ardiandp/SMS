<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AcademicEventService;
use App\Domain\Administration\AnnouncementService;
use App\Domain\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly AcademicEventService $events,
        private readonly AnnouncementService $announcements,
    ) {}

    public function __invoke(Request $request): View
    {
        $school = $this->currentSchool->school();
        $roles = $request->user()->getRoleNames();

        return view('dashboard', [
            'school' => $school,
            'roles' => $roles,
            // Pengumuman terbit untuk role user ini + kegiatan mendatang.
            // Kebocoran lintas tenant dicegah SchoolScope pada model.
            'announcements' => $this->announcements->visibleTo($roles->all(), 5),
            'upcomingEvents' => $this->events->upcoming(5),
        ]);
    }
}
