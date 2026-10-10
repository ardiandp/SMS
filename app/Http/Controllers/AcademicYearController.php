<?php

namespace App\Http\Controllers;

use App\Domain\Academics\AcademicYearService;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function __construct(private readonly AcademicYearService $academicYears) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AcademicYear::class);

        $years = AcademicYear::query()
            ->with('semesters')
            ->orderByDesc('starts_on')
            ->paginate(15)
            ->withQueryString();

        return view('academics.academic-years.index', ['years' => $years]);
    }

    public function create(): View
    {
        $this->authorize('create', AcademicYear::class);

        return view('academics.academic-years.create');
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        $this->academicYears->create($request->validated(), $request->user());

        return redirect()->route('academic-years.index')
            ->with('status', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function show(AcademicYear $academicYear): View
    {
        $this->authorize('view', $academicYear);

        $academicYear->load(['semesters', 'studyGroups.gradeLevel']);

        return view('academics.academic-years.show', [
            'academicYear' => $academicYear,
        ]);
    }

    public function edit(AcademicYear $academicYear): View
    {
        $this->authorize('update', $academicYear);

        return view('academics.academic-years.edit', ['academicYear' => $academicYear]);
    }

    public function update(StoreAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $this->academicYears->update($academicYear, $request->validated(), $request->user());

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Tahun ajaran berhasil diperbarui.');
    }

    /**
     * Make this the only active year for the school.
     */
    public function activate(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $this->academicYears->activate($academicYear, $request->user());

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Tahun ajaran aktif telah diperbarui.');
    }

    public function deactivate(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $this->academicYears->deactivate($academicYear, $request->user());

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Tahun ajaran tidak lagi aktif.');
    }

    /**
     * Semesters belong to a year; keep the flow on the year detail screen.
     */
    public function storeSemester(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'sequence' => ['required', 'integer', 'between:1,20'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        $duplicate = Semester::query()
            ->where('academic_year_id', $academicYear->getKey())
            ->where('sequence', $data['sequence'])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'sequence' => 'Urutan semester sudah dipakai pada tahun ajaran ini.',
            ]);
        }

        $academicYear->semesters()->create($data + ['school_id' => $academicYear->school_id]);

        return redirect()->route('academic-years.show', $academicYear)
            ->with('status', 'Semester berhasil ditambahkan.');
    }
}
