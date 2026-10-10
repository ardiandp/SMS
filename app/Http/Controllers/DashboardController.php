<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function __invoke(Request $request): View
    {
        $school = $this->currentSchool->school();

        return view('dashboard', [
            'school' => $school,
            'roles' => $request->user()->getRoleNames(),
        ]);
    }
}
