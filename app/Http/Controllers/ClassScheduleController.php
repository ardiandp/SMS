<?php

namespace App\Http\Controllers;

use App\Domain\Academics\ScheduleService;
use App\Http\Requests\StoreClassScheduleRequest;
use App\Models\ClassSchedule;
use App\Models\StudyGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules) {}

    public function index(Request $request, StudyGroup $studyGroup): View
    {
        $this->authorize('view', $studyGroup);

        return view('academics.schedules.index', [
            'studyGroup' => $studyGroup,
            'assignments' => $studyGroup->teachingAssignments()
                ->with('subject', 'employee')
                ->where('status', 'active')
                ->get(),
        ]);
    }

    public function store(StoreClassScheduleRequest $request, StudyGroup $studyGroup): RedirectResponse
    {
        $this->authorize('create', ClassSchedule::class);

        // The assignment must belong to the study group being scheduled.
        abort_unless(
            $studyGroup->teachingAssignments()->whereKey($request->integer('teaching_assignment_id'))->exists(),
            422,
            'Penugasan guru tidak terdaftar pada rombel ini.',
        );

        $schedule = $this->schedules->create(
            [
                'teaching_assignment_id' => $request->integer('teaching_assignment_id'),
                'day_of_week' => $request->integer('day_of_week'),
                'starts_at' => $request->string('starts_at')->toString(),
                'ends_at' => $request->string('ends_at')->toString(),
                'effective_from' => $request->input('effective_from'),
                'effective_until' => $request->input('effective_until'),
                'room_label' => $request->input('room_label'),
            ],
            $request->user(),
        );

        return redirect()->route('study-groups.schedules.index', $studyGroup)
            ->with('status', 'Jadwal pelajaran berhasil ditambahkan.');
    }

    public function update(StoreClassScheduleRequest $request, ClassSchedule $classSchedule): RedirectResponse
    {
        $this->authorize('update', $classSchedule);

        $this->schedules->update(
            $classSchedule,
            [
                'teaching_assignment_id' => $request->integer('teaching_assignment_id'),
                'day_of_week' => $request->integer('day_of_week'),
                'starts_at' => $request->string('starts_at')->toString(),
                'ends_at' => $request->string('ends_at')->toString(),
                'effective_from' => $request->input('effective_from'),
                'effective_until' => $request->input('effective_until'),
                'room_label' => $request->input('room_label'),
            ],
            $request->user(),
        );

        return back()->with('status', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(ClassSchedule $classSchedule): RedirectResponse
    {
        $this->authorize('delete', $classSchedule);

        $this->schedules->delete($classSchedule, request()->user());

        return back()->with('status', 'Jadwal pelajaran berhasil dihapus.');
    }
}
