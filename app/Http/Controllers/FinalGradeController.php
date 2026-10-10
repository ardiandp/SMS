<?php

namespace App\Http\Controllers;

use App\Domain\Assessment\FinalGradeService;
use App\Domain\Assessment\GradingPolicyService;
use App\Http\Requests\CorrectFinalGradeRequest;
use App\Models\FinalGrade;
use App\Models\TeachingAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinalGradeController extends Controller
{
    public function __construct(
        private readonly FinalGradeService $grades,
        private readonly GradingPolicyService $policies,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FinalGrade::class);

        $assignment = null;

        if ($request->filled('teaching_assignment_id')) {
            $assignment = TeachingAssignment::query()
                ->with(['studyGroup', 'subject'])
                ->find($request->integer('teaching_assignment_id'));
        }

        $grades = collect();
        $activePolicy = null;

        if ($assignment !== null) {
            $grades = FinalGrade::query()
                ->where('teaching_assignment_id', $assignment->getKey())
                ->with('student')
                ->get()
                ->sortBy(fn (FinalGrade $grade): string => $grade->student?->full_name ?? '')
                ->values();

            $yearId = (int) ($assignment->semester?->academic_year_id ?? 0);
            $activePolicy = $yearId > 0 ? $this->policies->activeForYear($yearId) : null;
        }

        return view('assessments.final-grades', [
            'assignment' => $assignment,
            'grades' => $grades,
            'activePolicy' => $activePolicy,
            'assignments' => TeachingAssignment::query()
                ->with(['studyGroup', 'subject'])
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function calculate(Request $request, TeachingAssignment $teachingAssignment): RedirectResponse
    {
        $this->authorize('calculate', FinalGrade::class);

        $yearId = (int) ($teachingAssignment->semester?->academic_year_id ?? 0);
        $policy = $this->policies->activeForYear($yearId);

        abort_unless(
            $policy !== null && $policy->getKey() === $request->integer('grading_policy_id'),
            422,
            'Kebijakan penilaian tidak aktif untuk tahun ajaran ini.',
        );

        $result = $this->grades->calculateForAssignment($teachingAssignment, $policy, $request->user());

        return redirect()
            ->route('final-grades.index', ['teaching_assignment_id' => $teachingAssignment->getKey()])
            ->with('status', "{$result['calculated']} nilai akhir dihitung. {$result['skipped_finalized']} nilai difinalisasi dilewati.");
    }

    public function finalize(Request $request, FinalGrade $finalGrade): RedirectResponse
    {
        $this->authorize('finalize', $finalGrade);

        $this->grades->finalize($finalGrade, $request->user());

        return redirect()
            ->route('final-grades.index', ['teaching_assignment_id' => $finalGrade->teaching_assignment_id])
            ->with('status', 'Nilai akhir difinalisasi.');
    }

    public function correct(CorrectFinalGradeRequest $request, FinalGrade $finalGrade): RedirectResponse
    {
        $this->grades->correct(
            $finalGrade,
            (float) $request->input('numeric_score'),
            $request->filled('grade_label') ? $request->string('grade_label')->toString() : null,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return redirect()
            ->route('final-grades.index', ['teaching_assignment_id' => $finalGrade->teaching_assignment_id])
            ->with('status', 'Nilai akhir dikoreksi. Nilai sebelumnya tersimpan di log audit.');
    }
}
