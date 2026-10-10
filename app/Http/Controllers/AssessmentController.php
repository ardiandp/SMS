<?php

namespace App\Http\Controllers;

use App\Domain\Assessment\AssessmentScoreService;
use App\Domain\Assessment\AssessmentService;
use App\Http\Requests\CorrectAssessmentScoreRequest;
use App\Http\Requests\EnterAssessmentScoresRequest;
use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentScore;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentService $assessments,
        private readonly AssessmentScoreService $scores,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Assessment::class);

        $assessments = Assessment::query()
            ->with(['teachingAssignment.studyGroup', 'teachingAssignment.subject', 'category'])
            ->withCount(['scores' => fn ($query) => $query->whereNotNull('score')])
            ->when($request->filled('status'), fn ($query) => $query
                ->where('status', $request->string('status')->toString()))
            ->when($request->filled('teaching_assignment_id'), fn ($query) => $query
                ->where('teaching_assignment_id', $request->integer('teaching_assignment_id')))
            ->orderByDesc('assessment_date')
            ->paginate(20)
            ->withQueryString();

        return view('assessments.index', [
            'assessments' => $assessments,
            'categories' => AssessmentCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAssessmentRequest $request): RedirectResponse
    {
        $assessment = $this->assessments->create($request->assessmentData(), $request->user());

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Asesmen dibuat. Silakan isi nilai.');
    }

    public function show(Assessment $assessment): View
    {
        $this->authorize('view', $assessment);

        $assessment->load(['teachingAssignment.studyGroup', 'teachingAssignment.subject', 'category', 'scores']);

        $eligibleIds = $this->scores->eligibleStudentIds($assessment);
        $scoresByStudent = $assessment->scores->keyBy('student_id');
        $students = Student::query()->whereIn('id', $eligibleIds)->get()->keyBy('id');

        // One row per roster student, whether or not a score exists yet.
        $rows = collect($eligibleIds)->map(function (int $studentId) use ($scoresByStudent, $students): array {
            $score = $scoresByStudent->get($studentId);

            return [
                'student_id' => $studentId,
                'student' => $students->get($studentId) ?? $score?->student,
                'score' => $score?->score,
                'feedback' => $score?->feedback,
            ];
        })->sortBy(fn (array $row) => $row['student']?->full_name ?? '')->values();

        return view('assessments.show', [
            'assessment' => $assessment,
            'rows' => $rows,
        ]);
    }

    public function update(StoreAssessmentRequest $request, Assessment $assessment): RedirectResponse
    {
        $this->assessments->update($assessment, $request->assessmentData(), $request->user());

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Asesmen diperbarui.');
    }

    public function saveScores(EnterAssessmentScoresRequest $request, Assessment $assessment): RedirectResponse
    {
        $written = $this->scores->saveDraftScores(
            $assessment,
            $request->scoresByStudentId(),
            $request->user(),
        );

        return redirect()->route('assessments.show', $assessment)
            ->with('status', "{$written} nilai disimpan.");
    }

    public function publish(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->authorize('publish', $assessment);

        $this->assessments->publish($assessment, $request->user());

        return redirect()->route('assessments.show', $assessment)
            ->with('status', 'Asesmen diterbitkan. Nilai difinalisasi; perubahan berikutnya tercatat sebagai koreksi.');
    }

    public function correct(CorrectAssessmentScoreRequest $request, AssessmentScore $assessmentScore): RedirectResponse
    {
        $this->scores->correctScore(
            $assessmentScore,
            (float) $request->input('score'),
            $request->string('reason')->toString(),
            $request->user(),
        );

        return redirect()->route('assessments.show', $assessmentScore->assessment)
            ->with('status', 'Nilai dikoreksi. Nilai sebelumnya tersimpan dalam jejak koreksi.');
    }

    public function destroy(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->authorize('delete', $assessment);

        $this->assessments->delete($assessment, $request->user());

        return redirect()->route('assessments.index')
            ->with('status', 'Asesmen draft dihapus.');
    }
}
