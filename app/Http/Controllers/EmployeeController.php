<?php

namespace App\Http\Controllers;

use App\Domain\Staff\EmployeeService;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::query()->orderBy('full_name');

        if (($search = trim((string) $request->query('q'))) !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        return view('employees.index', [
            'employees' => $query->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('employees.create');
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->employees->create($request->validated(), $request->user());

        return redirect()->route('employees.index')
            ->with('status', 'Pegawai berhasil ditambahkan.');
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);

        $employee->load(['assignments', 'userLink.user']);

        return view('employees.show', ['employee' => $employee]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.edit', ['employee' => $employee]);
    }

    public function update(StoreEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $this->employees->update($employee, $request->validated(), $request->user());

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Pegawai berhasil diperbarui.');
    }

    /**
     * Deactivate rather than delete: employee records back teaching assignments and
     * employment history, so they are never hard deleted (AGENTS.md rule 9).
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $this->employees->deactivate($employee, $request->user());

        return redirect()->route('employees.index')
            ->with('status', 'Pegawai dinonaktifkan.');
    }
}
