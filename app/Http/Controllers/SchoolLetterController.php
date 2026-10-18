<?php

namespace App\Http\Controllers;

use App\Domain\Administration\SchoolLetterService;
use App\Http\Requests\StoreSchoolLetterRequest;
use App\Models\LetterTemplate;
use App\Models\SchoolLetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolLetterController extends Controller
{
    public function __construct(private readonly SchoolLetterService $letters) {}

    public function index(): View
    {
        $this->authorize('viewAny', SchoolLetter::class);

        return view('administration.school_letters', [
            'templates' => LetterTemplate::where('is_active', true)
                ->orderByDesc('version')
                ->get(),
            'letters' => SchoolLetter::with('template')
                ->latest('created_at')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('administration.school_letters.form', ['letter' => new SchoolLetter()]);
    }

    public function store(StoreSchoolLetterRequest $request): RedirectResponse
    {
        $letter = $this->letters->createDraft(
            $request->validated(),
            $request->validated()['variables'] ?? [],
            $request->user(),
        );

        return redirect()->route('school_letters.index')
            ->with("status", "Surat {$letter->letter_number} draf dibuat.");
    }

    public function edit(SchoolLetter $schoolLetter): View
    {
        return view('administration.school_letters.form', ['letter' => $schoolLetter]);
    }

    public function update(StoreSchoolLetterRequest $request, SchoolLetter $schoolLetter): RedirectResponse
    {
        $updated = $this->letters->updateDraft(
            $schoolLetter,
            $request->validated(),
            $request->validated()['variables'] ?? [],
            $request->user(),
        );

        return redirect()->route('school_letters.index')
            ->with("status", "Surat {$updated->letter_number} draf diperbarui.");
    }

    public function show(SchoolLetter $schoolLetter): View
    {
        $this->authorize('view', $schoolLetter);

        return view('administration.school_letters.show', ['letter' => $schoolLetter]);
    }

    public function issue(Request $request, SchoolLetter $schoolLetter): RedirectResponse
    {
        $this->authorize('issue', $schoolLetter);

        $issued = $this->letters->issue($schoolLetter, $request->user());

        return redirect()->route('school_letters.index')
            ->with("status", "Surat {$issued->letter_number} terbit.");
    }

    public function cancel(Request $request, SchoolLetter $schoolLetter): RedirectResponse
    {
        $this->authorize('cancel', $schoolLetter);

        $cancelled = $this->letters->cancel($schoolLetter, $request->user());

        return redirect()->route('school_letters.index')
            ->with("status", "Surat {$cancelled->letter_number} dibatalkan.");
    }
}