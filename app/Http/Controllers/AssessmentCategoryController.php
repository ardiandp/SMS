<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\AssessmentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Assessment categories are school-configured data referenced by grading
 * policies through their slug, which is therefore unique per school and kept
 * stable on update.
 */
class AssessmentCategoryController extends Controller
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', AssessmentCategory::class);

        return view('assessments.categories', [
            'categories' => AssessmentCategory::query()
                ->withCount('assessments')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AssessmentCategory::class);

        $data = $this->validated($request);

        $category = new AssessmentCategory($data);
        $category->assignToCurrentSchool();
        $category->save();

        $this->audit->record(
            action: 'assessment_category.created',
            school: $this->currentSchool->school(),
            actor: $request->user(),
            subject: $category,
            metadata: ['assessment_category_id' => $category->getKey()],
        );

        return redirect()->route('assessments.categories.index')
            ->with('status', 'Kategori asesmen ditambahkan.');
    }

    public function update(Request $request, AssessmentCategory $assessmentCategory): RedirectResponse
    {
        $this->authorize('update', $assessmentCategory);

        $assessmentCategory->fill($this->validated($request, $assessmentCategory));
        $assessmentCategory->save();

        $this->audit->record(
            action: 'assessment_category.updated',
            school: $this->currentSchool->school(),
            actor: $request->user(),
            subject: $assessmentCategory,
            metadata: ['assessment_category_id' => $assessmentCategory->getKey()],
        );

        return redirect()->route('assessments.categories.index')
            ->with('status', 'Kategori asesmen diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AssessmentCategory $category = null): array
    {
        $schoolId = $this->currentSchool->school()?->getKey();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('assessment_categories', 'slug')
                    ->where('school_id', $schoolId)
                    ->ignore($category?->getKey()),
            ],
            'default_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['default_weight'] = $data['default_weight'] ?? null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
