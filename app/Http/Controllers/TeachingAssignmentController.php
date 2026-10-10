<?php

namespace App\Http\Controllers;

use App\Domain\Academics\TeachingAssignmentService;
use App\Http\Requests\StoreTeachingAssignmentRequest;
use App\Models\StudyGroup;
use App\Models\TeachingAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeachingAssignmentController extends Controller
{
    public function __construct(private readonly TeachingAssignmentService $assignments) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', TeachingAssignment::class);

        $assignments = TeachingAssignment::query()
            ->with(['studyGroup', 'subject', 'employee', 'semester'])
            ->when($request->filled('study_group_id'), fn ($query) => $query
                ->where('study_group_id', $request->integer('study_group_id')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('academics.teaching-assignments.index', [
            'assignments' => $assignments,
        ]);
    }

    public function store(StoreTeachingAssignmentRequest $request): RedirectResponse
    {
        $assignment = $this->assignments->create(
            $request->validated(),
            $request->user(),
        );

        return redirect()->route('study-groups.show', $assignment->study_group_id)
            ->with('status', 'Penugasan guru berhasil dibuat.');
    }

    public function update(StoreTeachingAssignmentRequest $request, TeachingAssignment $teachingAssignment): RedirectResponse
    {
        $this->authorize('update', $teachingAssignment);

        $this->assignments->update($teachingAssignment, $request->validated(), $request->user());

        return redirect()->route('study-groups.show', $teachingAssignment->study_group_id)
            ->with('status', 'Penugasan guru berhasil diperbarui.');
    }

    public function destroy(TeachingAssignment $teachingAssignment): RedirectResponse
    {
        $this->authorize('delete', $teachingAssignment);

        $studyGroupId = $teachingAssignment->study_group_id;

        if ($teachingAssignment->schedules()->exists()) {
            return back()->withErrors([
                'schedules' => 'Hapus jadwal pelajaran terlebih dahulu sebelum menghapus penugasan.',
            ]);
        }

        $teachingAssignment->delete();

        return redirect()->route('study-groups.show', $studyGroupId)
            ->with('status', 'Penugasan guru berhasil dihapus.');
    }

    /**
     * Schedules live inside a study group view, but the resource routes are kept
     * top level so conflict errors can redirect sensibly.
     */
    public function schedules(Request $request, StudyGroup $studyGroup): View
    {
        $this->authorize('view', $studyGroup);

        return view('academics.schedules.index', [
            'studyGroup' => $studyGroup,
            'assignments' => $studyGroup->teachingAssignments()
                ->with('subject', 'employee')
                ->where('status', 'active')
                ->get(),
            'schedules' => $studyGroup->teachingAssignments()
                ->with(['subject', 'employee', 'schedules'])
                ->get()
                ->flatMap(fn (TeachingAssignment $assignment) => $assignment->schedules
                    ->map(fn ($schedule) => [
                        'schedule' => $schedule,
                        'assignment' => $assignment,
                    ]))
                ->sortByDesc(fn ($row) => $row['schedule']->id),
        ]);
    }
}
