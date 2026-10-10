<?php

namespace App\Http\Controllers;

use App\Domain\Assessment\GradingPolicyService;
use App\Http\Requests\StoreGradingPolicyRequest;
use App\Models\AcademicYear;
use App\Models\AssessmentCategory;
use App\Models\GradingPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradingPolicyController extends Controller
{
    public function __construct(private readonly GradingPolicyService $policies) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GradingPolicy::class);

        $policies = GradingPolicy::query()
            ->with('academicYear')
            ->when($request->filled('academic_year_id'), fn ($query) => $query
                ->where('academic_year_id', $request->integer('academic_year_id')))
            ->orderBy('name')
            ->orderByDesc('version')
            ->paginate(20)
            ->withQueryString();

        return view('assessments.grading-policies', [
            'policies' => $policies,
            'academicYears' => AcademicYear::orderByDesc('starts_on')->get(),
            'categories' => AssessmentCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreGradingPolicyRequest $request): RedirectResponse
    {
        $policy = $this->policies->createDraft([
            'name' => $request->string('name')->toString(),
            'academic_year_id' => $request->integer('academic_year_id'),
            'config' => $request->policyConfig(),
        ], $request->user());

        return redirect()->route('grading-policies.index')
            ->with('status', "Kebijakan penilaian versi {$policy->version} dibuat sebagai draft.");
    }

    public function activate(Request $request, GradingPolicy $gradingPolicy): RedirectResponse
    {
        $this->authorize('activate', $gradingPolicy);

        $this->policies->activate($gradingPolicy, $request->user());

        return redirect()->route('grading-policies.index')
            ->with('status', 'Kebijakan penilaian diaktifkan. Versi sebelumnya diarsipkan.');
    }
}
