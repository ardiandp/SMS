<?php

namespace App\Http\Controllers;

use App\Domain\Assessment\ReportCardService;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function __construct(private readonly ReportCardService $reportCards) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReportCard::class);

        $student = null;
        $year = null;
        $semester = null;
        $revisions = collect();
        $latest = null;

        if ($request->filled('student_id')) {
            $student = Student::find($request->integer('student_id'));
        }

        if ($request->filled('academic_year_id') && $request->filled('semester_id')) {
            $year = AcademicYear::find($request->integer('academic_year_id'));
            $semester = Semester::find($request->integer('semester_id'));
        }

        if ($student !== null && $year !== null && $semester !== null) {
            $revisions = $this->reportCards->revisions($student, $year, $semester);
            $latest = $revisions->first();
        }

        return view('assessments.report-cards', [
            'student' => $student,
            'year' => $year,
            'semester' => $semester,
            'revisions' => $revisions,
            'latest' => $latest,
            'students' => Student::orderBy('full_name')->limit(200)->get(),
            'academicYears' => AcademicYear::orderByDesc('starts_on')->get(),
            'semesters' => Semester::orderBy('sequence')->get(),
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $this->authorize('publish', ReportCard::class);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $semester = Semester::findOrFail($data['semester_id']);

        $card = $this->reportCards->publish($student, $year, $semester, $request->user());

        return redirect()
            ->route('report-cards.show', $card)
            ->with('status', "Rapor diterbitkan (revisi ke-{$card->revision_no}).");
    }

    /**
     * One published revision, print-friendly.
     */
    public function show(Request $request, ReportCard $reportCard): View
    {
        $this->authorize('view', $reportCard);

        $reportCard->load([
            'items.subject',
            'items.teacher',
            'student',
            'academicYear',
            'semester',
        ]);

        $previous = ReportCard::query()
            ->where('student_id', $reportCard->student_id)
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('semester_id', $reportCard->semester_id)
            ->where('revision_no', '<', $reportCard->revision_no)
            ->orderByDesc('revision_no')
            ->first();

        return view('assessments.report-cards.show', [
            'card' => $reportCard,
            'previous' => $previous,
        ]);
    }
}
