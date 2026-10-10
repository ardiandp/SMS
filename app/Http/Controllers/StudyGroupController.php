<?php

namespace App\Http\Controllers;

use App\Domain\Academics\StudyGroupService;
use App\Http\Requests\StoreStudyGroupRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudyGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudyGroupController extends Controller
{
    public function __construct(private readonly StudyGroupService $studyGroups) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StudyGroup::class);

        $groups = StudyGroup::query()
            ->with(['academicYear', 'gradeLevel', 'classroom', 'homeroomTeacher'])
            ->withCount('members')
            ->when($request->filled('academic_year_id'), fn ($query) => $query
                ->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('grade_level_id'), fn ($query) => $query
                ->where('grade_level_id', $request->integer('grade_level_id')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('academics.study-groups.index', [
            'groups' => $groups,
            'academicYears' => AcademicYear::orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::where('is_active', true)->orderBy('sequence')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StudyGroup::class);

        return view('academics.study-groups.create', $this->formOptions());
    }

    public function store(StoreStudyGroupRequest $request): RedirectResponse
    {
        $group = $this->studyGroups->create(
            $request->safe()->except(['capacity']) + [
                'classroom_id' => $request->classroomId(),
                'homeroom_employee_id' => $request->homeroomEmployeeId(),
                'capacity' => $request->input('capacity'),
            ],
            $request->user(),
        );

        return redirect()->route('study-groups.show', $group)
            ->with('status', 'Rombel berhasil ditambahkan.');
    }

    public function show(StudyGroup $studyGroup): View
    {
        $this->authorize('view', $studyGroup);

        $studyGroup->load([
            'academicYear',
            'gradeLevel',
            'classroom',
            'homeroomTeacher',
            'members' => fn ($query) => $query->where('status', 'active')->with('student'),
            'teachingAssignments.subject',
            'teachingAssignments.employee',
        ]);

        return view('academics.study-groups.show', [
            'studyGroup' => $studyGroup,
            'availableStudents' => Student::query()
                ->whereNotIn('id', $studyGroup->members->pluck('student_id'))
                ->orderBy('full_name')
                ->limit(50)
                ->get(),
        ]);
    }

    public function edit(StudyGroup $studyGroup): View
    {
        $this->authorize('update', $studyGroup);

        return view('academics.study-groups.edit', [
            'studyGroup' => $studyGroup,
            ...$this->formOptions(),
        ]);
    }

    public function update(StoreStudyGroupRequest $request, StudyGroup $studyGroup): RedirectResponse
    {
        $this->authorize('update', $studyGroup);

        $this->studyGroups->update(
            $studyGroup,
            $request->safe()->except(['capacity']) + [
                'classroom_id' => $request->classroomId(),
                'homeroom_employee_id' => $request->homeroomEmployeeId(),
                'capacity' => $request->input('capacity'),
            ],
            $request->user(),
        );

        return redirect()->route('study-groups.show', $studyGroup)
            ->with('status', 'Rombel berhasil diperbarui.');
    }

    /**
     * Place a student into this group. Any previous active placement is closed,
     * never deleted, so the history stays readable.
     */
    public function addStudent(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        $this->authorize('manageMembers', $studyGroup);

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'starts_on' => ['nullable', 'date'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        $this->studyGroups->placeStudent(
            $studyGroup,
            $student,
            $data['starts_on'] ?? null,
            $request->user(),
        );

        return redirect()->route('study-groups.show', $studyGroup)
            ->with('status', 'Siswa berhasil dimasukkan ke rombel.');
    }

    public function removeStudent(Request $request, StudyGroup $studyGroup, Student $student): RedirectResponse
    {
        $this->authorize('manageMembers', $studyGroup);

        $this->studyGroups->removeStudent($studyGroup, $student, $request->user());

        return redirect()->route('study-groups.show', $studyGroup)
            ->with('status', 'Siswa dikeluarkan dari rombel.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'academicYears' => AcademicYear::orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::where('is_active', true)->orderBy('sequence')->get(),
            'classrooms' => Classroom::orderBy('name')->get(),
            'employees' => Employee::orderBy('full_name')->get(),
        ];
    }
}
