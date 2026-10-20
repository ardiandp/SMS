<?php

namespace App\Http\Controllers;

use App\Domain\Academics\QuizService;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AcademicYear;
use App\Models\Domain\Academics\Quiz;
use App\Models\Subject;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuizzesController extends Controller
{
    public function __construct(
        private readonly QuizService $service,
        private readonly CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quiz::class);

        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $academicYearId = $request->integer('academic_year_id');
        $subjectId = $request->integer('subject_id');

        $query = $this->service->getActive($schoolId)
            ->with(['academicYear', 'subject', 'studyGroup']);

        if ($academicYearId) {
            $query = $query->where('academic_year_id', $academicYearId);
        }

        if ($subjectId) {
            $query = $query->where('subject_id', $subjectId);
        }

        $quizzes = $query->get();

        $academicYears = AcademicYear::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $studyGroups = StudyGroup::orderBy('name')->get();

        return view('quizzes.index', [
            'quizzes' => $quizzes,
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'studyGroups' => $studyGroups,
            'selectedAcademicYear' => $academicYearId,
            'selectedSubject' => $subjectId,
            'selectedStudyGroup' => $request->integer('study_group_id'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quiz::class);

        $schoolId = (int) $this->currentSchool->school()?->getKey() ?? $request->integer('school_id');

        $academicYears = AcademicYear::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $studyGroups = StudyGroup::orderBy('name')->get();

        return view('quizzes.create', [
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'studyGroups' => $studyGroups,
            'schoolId' => $schoolId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Quiz::class);

        $data = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'study_group_id' => 'required|integer|exists:study_groups,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'time_limit' => 'sometimes|integer|min:1',
            'passing_score' => 'sometimes|integer|min:0|max:100',
        ]);

        $this->service->create($data, $this->getCurrentUser());

        return redirect()->route('quizzes.index')
            ->with('success', 'Kuis berhasil dibuat.');
    }

    public function show(Request $request, int $id): View
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $quiz = Quiz::with(['academicYear', 'subject', 'studyGroup', 'questions'])
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('view', $quiz);

        return view('quizzes.show', [
            'quiz' => $quiz,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey() ?? $request->integer('school_id');
        $quiz = Quiz::with(['academicYear', 'subject', 'studyGroup'])
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('update', $quiz);

        $academicYears = AcademicYear::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $studyGroups = StudyGroup::orderBy('name')->get();

        return view('quizzes.edit', [
            'quiz' => $quiz,
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'studyGroups' => $studyGroups,
            'schoolId' => $schoolId,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $quiz = Quiz::with(['academicYear', 'subject', 'studyGroup'])
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('update', $quiz);

        $data = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'study_group_id' => 'required|integer|exists:study_groups,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'time_limit' => 'sometimes|integer|min:1',
            'passing_score' => 'sometimes|integer|min:0|max:100',
        ]);

        $this->service->update($quiz, $data, $this->getCurrentUser());

        return redirect()->route('quizzes.index')
            ->with('success', 'Kuis berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $quiz = Quiz::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('delete', $quiz);

        $this->service->delete($quiz, $this->getCurrentUser());

        return redirect()->route('quizzes.index')
            ->with('success', 'Kuis berhasil dinonaktifkan.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $quiz = Quiz::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('restore', $quiz);

        $this->service->restore($quiz, $this->getCurrentUser());

        return redirect()->route('quizzes.index')
            ->with('success', 'Kuis berhasil diaktifkan.');
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
