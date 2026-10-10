<?php

namespace App\Http\Controllers;

use App\Domain\Students\StudentService;
use App\Enums\StudentStatus;
use App\Http\Requests\LinkGuardianRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    /**
     * Server-side search + pagination, scoped to the current tenant.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Student::class);

        $query = Student::query()->orderBy('full_name');

        if (($search = trim((string) $request->query('q'))) !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if (($status = (string) $request->query('status')) !== '' && StudentStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        return view('students.index', [
            'students' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Student::class);

        return view('students.create');
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = $this->students->create($request->validated(), $request->user());

        return redirect()->route('students.show', $student)
            ->with('status', 'Siswa berhasil ditambahkan.');
    }

    public function show(Student $student): View
    {
        $this->authorize('view', $student);

        $student->load(['guardians', 'documents', 'statusHistories']);

        return view('students.show', ['student' => $student]);
    }

    public function edit(Student $student, Request $request): View
    {
        $this->authorize('update', $student);

        return view('students.edit', ['student' => $student]);
    }

    public function update(StoreStudentRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);

        $this->students->update($student, $request->validated(), $request->user());

        return redirect()->route('students.show', $student)
            ->with('status', 'Siswa berhasil diperbarui.');
    }

    /**
     * Link a guardian to this student (both must belong to the same tenant).
     */
    public function linkGuardian(LinkGuardianRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);

        $this->students->linkGuardian(
            $student,
            $request->guardian(),
            $request->string('relationship')->toString(),
            $request->user(),
        );

        return redirect()->route('students.show', $student)
            ->with('status', 'Wali berhasil ditautkan.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);

        $this->students->deactivate($student, $request->user());

        return redirect()->route('students.index')
            ->with('status', 'Siswa dinonaktifkan.');
    }
}
