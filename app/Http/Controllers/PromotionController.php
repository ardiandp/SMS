<?php

namespace App\Http\Controllers;

use App\Domain\Academics\PromotionService;
use App\Enums\PromotionOutcome;
use App\Http\Requests\IssueGraduationRequest;
use App\Http\Requests\RecordPromotionDecisionRequest;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\PromotionDecision;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PromotionDecision::class);

        $year = null;
        $decisions = collect();
        $graduations = collect();

        if ($request->filled('academic_year_id')) {
            $year = AcademicYear::find($request->integer('academic_year_id'));

            if ($year !== null) {
                $decisions = $this->promotions->decisionsForYear($year);
                $graduations = $this->promotions->graduationsForYear($year);
            }
        }

        return view('academics.promotions', [
            'year' => $year,
            'decisions' => $decisions,
            'graduations' => $graduations,
            'academicYears' => AcademicYear::orderByDesc('starts_on')->get(),
            'students' => Student::orderBy('full_name')->limit(200)->get(),
            'gradeLevels' => GradeLevel::orderBy('sequence')->get(),
        ]);
    }

    public function decide(RecordPromotionDecisionRequest $request): RedirectResponse
    {
        $student = Student::findOrFail($request->integer('student_id'));
        $year = AcademicYear::findOrFail($request->integer('academic_year_id'));

        $decision = $this->promotions->recordDecision(
            $student,
            $year,
            PromotionOutcome::from($request->string('decision')->toString()),
            $this->resolveGradeLevel($request, 'from_grade_level_id'),
            $this->resolveGradeLevel($request, 'to_grade_level_id'),
            $request->filled('notes') ? $request->string('notes')->toString() : null,
            $request->user(),
        );

        return redirect()
            ->route('promotions.index', ['academic_year_id' => $year->getKey()])
            ->with('status', "Keputusan {$decision->decision->label()} untuk {$student->full_name} tersimpan.");
    }

    public function issue(IssueGraduationRequest $request): RedirectResponse
    {
        $student = Student::findOrFail($request->integer('student_id'));
        $year = AcademicYear::findOrFail($request->integer('academic_year_id'));

        $record = $this->promotions->issueGraduation(
            $student,
            $year,
            $request->string('document_number')->toString(),
            $request->user(),
        );

        return redirect()
            ->route('promotions.index', ['academic_year_id' => $year->getKey()])
            ->with('status', "Kelulusan {$student->full_name} diterbitkan (dokumen {$record->document_number}).");
    }

    private function resolveGradeLevel(Request $request, string $field): ?GradeLevel
    {
        if (! $request->filled($field)) {
            return null;
        }

        return GradeLevel::findOrFail($request->integer($field));
    }
}
