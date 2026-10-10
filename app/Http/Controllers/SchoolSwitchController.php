<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolSwitchController extends Controller
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * List every school the authenticated user has an active membership in.
     */
    public function select(): View
    {
        $schools = auth()->user()->accessibleSchools()->orderBy('name')->get();

        return view('schools.select', ['schools' => $schools]);
    }

    /**
     * Switch the active tenant after re-validating membership server-side.
     */
    public function switch(Request $request, School $school): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $this->currentSchool->resolve($user, $school->getKey()),
            403,
        );

        $request->session()->put('current_school_id', $school->getKey());

        $this->audit->record(
            action: 'tenant.switched',
            school: $school,
            actor: $user,
            subject: $school,
        );

        return redirect()->route('dashboard');
    }
}
