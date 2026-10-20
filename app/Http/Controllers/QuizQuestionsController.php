<?php

namespace App\Http\Controllers;

use App\Domain\Academics\QuizQuestionService;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AcademicYear;
use App\Models\Domain\Academics\QuizQuestion;
use App\Models\Subject;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizQuestionsController extends Controller
{
    public function __construct(
        private readonly QuizQuestionService $service,
        private readonly CurrentSchool $currentSchool,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QuizQuestion::class);

        $schoolId = (int) ($this->currentSchool->school()?->getKey() ?? $request->integer('school_id'));
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

        $questions = $query->get();

        $academicYears = AcademicYear::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $studyGroups = StudyGroup::orderBy('name')->get();

        $selectedSchool = $schoolId;
        $selectedAcademicYear = $academicYearId;
        $selectedSubject = $subjectId;

        return view('quiz-questions.index', [
            'questions' => $questions,
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'studyGroups' => $studyGroups,
            'selectedSchool' => $selectedSchool,
            'selectedAcademicYear' => $selectedAcademicYear,
            'selectedSubject' => $selectedSubject,
            'selectedStudyGroup' => $request->integer('study_group_id'),
            'users' => User::pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', QuizQuestion::class);

        $schoolId = (int) ($this->currentSchool->school()?->getKey() ?? $request->integer('school_id'));

        $academicYears = AcademicYear::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $studyGroups = StudyGroup::orderBy('name')->get();

        return view('quiz-questions.create', [
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'studyGroups' => $studyGroups,
            'users' => User::pluck('name', 'id'),
            'schoolId' => $schoolId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QuizQuestion::class);

        $data = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'study_group_id' => 'required|integer|exists:study_groups,id',
            'question_text' => 'required|string|max:2000',
            'choices' => 'required',
            'correct_choice_index' => 'required|integer|min:0',
            'difficulty' => 'sometimes|string|in:easy,medium,hard',
            'question_type' => 'sometimes|string|in:multiple_choice,true_false',
            'created_by' => 'sometimes|integer|exists:users,id',
        ]);

        $data['choices'] = $this->normalizeChoices($data['choices']);
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['updated_by'] = $data['created_by'];

        $this->service->create($data, $this->getCurrentUser());

        return redirect()->route('quiz-questions.index')
            ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function show(Request $request, int $id): View
    {
        $question = $this->service->getActive((int) ($this->currentSchool->school()?->getKey() ?? $request->integer('school_id')))
            ->with(['academicYear', 'subject', 'studyGroup'])
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('view', $question);

        return view('quiz-questions.show', [
            'question' => $question,
            'users' => User::pluck('name', 'id'),
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $schoolId = (int) ($this->currentSchool->school()?->getKey() ?? $request->integer('school_id'));
        $question = QuizQuestion::with(['academicYear', 'subject', 'studyGroup'])
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('update', $question);

        $academicYears = AcademicYear::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $studyGroups = StudyGroup::orderBy('name')->get();

        return view('quiz-questions.edit', [
            'question' => $question,
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'studyGroups' => $studyGroups,
            'users' => User::pluck('name', 'id'),
            'schoolId' => $schoolId,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $question = QuizQuestion::with(['academicYear', 'subject', 'studyGroup'])
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('update', $question);

        $data = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'study_group_id' => 'required|integer|exists:study_groups,id',
            'question_text' => 'required|string|max:2000',
            'choices' => 'required',
            'correct_choice_index' => 'required|integer|min:0',
            'difficulty' => 'sometimes|string|in:easy,medium,hard',
            'question_type' => 'sometimes|string|in:multiple_choice,true_false',
            'updated_by' => 'sometimes|integer|exists:users,id',
        ]);

        $data['choices'] = $this->normalizeChoices($data['choices']);
        $data['updated_by'] = $data['updated_by'] ?? Auth::id();

        $this->service->update($question, $data, $this->getCurrentUser());

        return redirect()->route('quiz-questions.index')
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $question = QuizQuestion::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('delete', $question);

        $this->service->delete($question, $this->getCurrentUser());

        return redirect()->route('quiz-questions.index')
            ->with('success', 'Soal berhasil dihapus.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $question = QuizQuestion::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('restore', $question);

        $this->service->restore($question, $this->getCurrentUser());

        return redirect()->route('quiz-questions.index')
            ->with('success', 'Soal berhasil dikembalikan.');
    }

    /** @return array<int|string, string> */
    private function normalizeChoices(mixed $choices): array
    {
        if (is_string($choices)) {
            $parts = array_map('trim', explode(',', $choices));
            $choices = array_values(array_filter($parts, fn ($part) => $part !== ''));
        }

        if (! is_array($choices)) {
            $choices = [$choices];
        }

        return array_values($choices);
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
