<?php

namespace App\Http\Controllers;

use App\Domain\Students\StudentImportService;
use App\Http\Requests\ImportUploadRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentImportController extends Controller
{
    public function __construct(private readonly StudentImportService $importer) {}

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('import.students'), 403);

        return view('students.import', [
            'preview' => $request->session()->get('import_preview'),
        ]);
    }

    /**
     * Step 1: upload → parse & validate → keep preview in session (nothing written).
     */
    public function preview(ImportUploadRequest $request): RedirectResponse
    {
        $preview = $this->importer->preview($request->file('file'));

        $request->session()->put('import_preview', $preview);

        return redirect()->route('students.import.create');
    }

    /**
     * Step 2: confirm → write all rows in one transaction (all-or-nothing).
     */
    public function commit(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('import.students'), 403);

        $preview = $request->session()->get('import_preview');

        if (! is_array($preview) || $preview['rows'] === []) {
            return redirect()->route('students.import.create')
                ->withErrors(['file' => 'Tidak ada data untuk diimpor. Unggah file terlebih dahulu.']);
        }

        $created = $this->importer->commit($preview['rows'], $request->user());

        $request->session()->forget('import_preview');

        return redirect()->route('students.index')
            ->with('status', "{$created} siswa berhasil diimpor.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('import_preview');

        return redirect()->route('students.import.create');
    }
}
