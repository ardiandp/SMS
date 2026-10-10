<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuardianRequest;
use App\Models\Guardian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Guardian::class);

        $query = Guardian::query()->orderBy('full_name');

        if (($search = trim((string) $request->query('q'))) !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return view('guardians.index', [
            'guardians' => $query->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Guardian::class);

        return view('guardians.create');
    }

    public function store(StoreGuardianRequest $request): RedirectResponse
    {
        $guardian = new Guardian($request->validated());
        $guardian->assignToCurrentSchool();
        $guardian->save();

        return redirect()->route('guardians.index')
            ->with('status', 'Wali berhasil ditambahkan.');
    }

    public function edit(Guardian $guardian): View
    {
        $this->authorize('update', $guardian);

        return view('guardians.edit', ['guardian' => $guardian]);
    }

    public function update(StoreGuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        $this->authorize('update', $guardian);

        $guardian->update($request->validated());

        return redirect()->route('guardians.index')
            ->with('status', 'Wali berhasil diperbarui.');
    }
}
