<?php

namespace App\Http\Controllers;

use App\Domain\Academics\QuizSubmissionService;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\Domain\Academics\Quiz;
use App\Models\Domain\Academics\QuizSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuizSubmissionsController extends Controller
{
    public function __construct(
        private readonly QuizSubmissionService $service,
        private readonly CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QuizSubmission::class);

        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $quizId = $request->integer('quiz_id');
        $studentId = $request->integer('student_id');
        $status = $request->string('status');

        $query = QuizSubmission::where('school_id', $schoolId)
            ->with(['quiz', 'student', 'grader']);

        if ($quizId) {
            $query = $query->where('quiz_id', $quizId);
        }

        if ($studentId) {
            $query = $query->where('student_id', $studentId);
        }

        if ($status) {
            $query = $query->where('status', $status);
        }

        $submissions = $query->get();

        $quizzes = Quiz::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        $students = Student::where('school_id', $schoolId)
            ->orderBy('full_name')
            ->get();

        return view('quiz-submissions.index', [
            'submissions' => $submissions,
            'quizzes' => $quizzes,
            'students' => $students,
            'selectedQuiz' => $quizId,
            'selectedStudent' => $studentId,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $submission = QuizSubmission::with(['quiz', 'student', 'grader'])
            ->where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('view', $submission);

        return view('quiz-submissions.show', [
            'submission' => $submission,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', QuizSubmission::class);

        $data = $request->validate([
            'quiz_id' => 'required|integer|exists:7a_quizzes,id',
            'student_id' => 'required|integer|exists:students,id',
            'answers' => 'required|array',
        ]);

        $this->service->create($data, $this->getCurrentUser());

        return redirect()->route('quiz-submissions.index')
            ->with('success', 'Submission kuis berhasil disimpan sebagai draft.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $submission = QuizSubmission::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('update', $submission);

        $data = $request->validate([
            'answers' => 'required|array',
        ]);

        $this->service->update($submission, $data, $this->getCurrentUser());

        return redirect()->route('quiz-submissions.index')
            ->with('success', 'Submission kuis berhasil diperbarui.');
    }

    public function submit(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $submission = QuizSubmission::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('submit', $submission);

        $this->service->submit($submission, $this->getCurrentUser());

        return redirect()->route('quiz-submissions.index')
            ->with('success', 'Submission kuis berhasil dikumpulkan.');
    }

    public function grade(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $submission = QuizSubmission::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('grade', $submission);

        $data = $request->validate([
            'score' => 'required|integer|min:0',
            'max_score' => 'sometimes|integer|min:1',
            'feedback' => 'nullable|string|max:2000',
        ]);

        $this->service->grade($submission, $data, $this->getCurrentUser());

        return redirect()->route('quiz-submissions.index')
            ->with('success', 'Submission kuis telah dinilai.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->school()?->getKey();
        $submission = QuizSubmission::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $this->authorize('delete', $submission);

        $this->service->delete($submission, $this->getCurrentUser());

        return redirect()->route('quiz-submissions.index')
            ->with('success', 'Submission kuis berhasil dihapus.');
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
