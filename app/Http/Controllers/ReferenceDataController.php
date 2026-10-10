<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    /**
     * Grade levels (kelas / jenjang). Jenjang is school-configured data, never a
     * hard-coded list.
     */
    public function gradeLevels(): View
    {
        $this->authorize('viewAny', GradeLevel::class);

        return view('academics.grade-levels.index', [
            'gradeLevels' => GradeLevel::query()
                ->withCount('studyGroups')
                ->orderBy('sequence')
                ->paginate(20),
        ]);
    }

    public function storeGradeLevel(Request $request): RedirectResponse
    {
        $this->authorize('create', GradeLevel::class);

        $gradeLevel = new GradeLevel($this->validatedGradeLevel($request));
        $gradeLevel->assignToCurrentSchool();
        $gradeLevel->save();

        return redirect()->route('academics.grade-levels.index')
            ->with('status', 'Kelas berhasil ditambahkan.');
    }

    public function updateGradeLevel(Request $request, GradeLevel $gradeLevel): RedirectResponse
    {
        $this->authorize('update', $gradeLevel);

        $gradeLevel->update($this->validatedGradeLevel($request, $gradeLevel));

        return redirect()->route('academics.grade-levels.index')
            ->with('status', 'Kelas berhasil diperbarui.');
    }

    public function subjects(): View
    {
        $this->authorize('viewAny', Subject::class);

        return view('academics.subjects.index', [
            'subjects' => Subject::query()
                ->withCount('teachingAssignments')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $this->authorize('create', Subject::class);

        $subject = new Subject($this->validatedSubject($request));
        $subject->assignToCurrentSchool();
        $subject->save();

        return redirect()->route('academics.subjects.index')
            ->with('status', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorize('update', $subject);

        $subject->update($this->validatedSubject($request, $subject));

        return redirect()->route('academics.subjects.index')
            ->with('status', 'Mata pelajaran berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedGradeLevel(Request $request, ?GradeLevel $gradeLevel = null): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('grade_levels', 'code')
                    ->where('school_id', $schoolId)
                    ->ignore($gradeLevel?->getKey()),
            ],
            'name' => ['required', 'string', 'max:100'],
            'education_level' => ['required', 'string', 'max:50'],
            'sequence' => ['required', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSubject(Request $request, ?Subject $subject = null): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('subjects', 'code')
                    ->where('school_id', $schoolId)
                    ->ignore($subject?->getKey()),
            ],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
