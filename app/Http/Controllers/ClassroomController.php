<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\CurrentSchool;
use App\Enums\ClassroomStatus;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Classroom::class);

        return view('academics.classrooms.index', [
            'classrooms' => Classroom::query()
                ->withCount('studyGroups')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Classroom::class);

        return view('academics.classrooms.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Classroom::class);

        $classroom = new Classroom($this->validated($request));
        $classroom->assignToCurrentSchool();
        $classroom->save();

        return redirect()->route('classrooms.index')
            ->with('status', 'Ruang kelas berhasil ditambahkan.');
    }

    public function edit(Classroom $classroom): View
    {
        $this->authorize('update', $classroom);

        return view('academics.classrooms.edit', ['classroom' => $classroom]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);

        $classroom->update($this->validated($request, $classroom));

        return redirect()->route('classrooms.index')
            ->with('status', 'Ruang kelas berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Classroom $classroom = null): array
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('classrooms', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($classroom?->getKey()),
            ],
            'building' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['required', Rule::enum(ClassroomStatus::class)],
        ]);
    }
}
