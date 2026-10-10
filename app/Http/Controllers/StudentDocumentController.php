<?php

namespace App\Http\Controllers;

use App\Domain\Students\DocumentService;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    /**
     * Upload a private document for a student.
     */
    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('upload', StudentDocument::class);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'document_type' => ['required', 'string', 'max:50'],
        ]);

        $this->documents->store(
            $request->file('file'),
            $student,
            $request->input('document_type'),
            $request->user(),
        );

        return redirect()->route('students.show', $student)
            ->with('status', 'Dokumen berhasil diunggah.');
    }

    /**
     * Download through controller + policy — files live on the private disk
     * and are never exposed under /storage.
     */
    public function download(Request $request, StudentDocument $document): BinaryFileResponse
    {
        $this->authorize('download', $document);

        $path = $this->documents->pathFor($document);

        abort_unless($path !== null, 404);

        return response()->download($path, basename($document->storage_path), [
            'Content-Type' => $document->mime_type,
        ]);
    }
}
