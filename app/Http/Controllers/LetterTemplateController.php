<?php

namespace App\Http\Controllers;

use App\Domain\Administration\LetterTemplateService;
use App\Http\Requests\StoreLetterTemplateRequest;
use App\Models\LetterTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LetterTemplateController extends Controller
{
    public function __construct(
        private readonly LetterTemplateService $templates,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', LetterTemplate::class);

        return view('administration.letter_templates', [
            'templates' => LetterTemplate::latest('version')
                ->where('is_active', true)
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('administration.letter_templates.form', ['template' => new LetterTemplate()]);
    }

    public function store(StoreLetterTemplateRequest $request): RedirectResponse
    {
        $template = $this->templates->create($request->validated(), $request->user());

        return redirect()->route('letter_templates.index')
            ->with('status', "Template {$template->code} dibuat (versi 1).");
    }

    public function edit(LetterTemplate $letterTemplate): View
    {
        return view('administration.letter_templates.form', ['template' => $letterTemplate]);
    }

    public function update(StoreLetterTemplateRequest $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        $revised = $this->templates->revise($letterTemplate, $request->validated(), $request->user());

        return redirect()->route('letter_templates.index')
            ->with('status', "Template {$revised->code} diperbarui menjadi versi {$revised->version}.");
    }

    public function destroy(LetterTemplate $letterTemplate): RedirectResponse
    {
        $this->authorize('delete', $letterTemplate);

        $code = $letterTemplate->code;
        $letterTemplate->delete();

        return redirect()->route('letter_templates.index')
            ->with('status', "Template {$code} dihapus.");
    }
}
