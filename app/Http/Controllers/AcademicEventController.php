<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AcademicEventService;
use App\Http\Requests\StoreAcademicEventRequest;
use App\Models\AcademicEvent;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicEventController extends Controller
{
    public function __construct(private readonly AcademicEventService $events) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AcademicEvent::class);

        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->addMonths(2)->endOfMonth();

        return view('administration.events', [
            'from' => $from,
            'to' => $to,
            'events' => $this->events->between(
                $from->toDateString().' 00:00:00',
                $to->toDateString().' 23:59:59',
            ),
            'academicYears' => AcademicYear::orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(StoreAcademicEventRequest $request): RedirectResponse
    {
        $event = $this->events->create($request->validated(), $request->user());

        return redirect()->route('academic-events.index')
            ->with('status', "Kegiatan {$event->title} tersimpan.");
    }

    public function update(StoreAcademicEventRequest $request, AcademicEvent $academicEvent): RedirectResponse
    {
        // Route-model binding sudah tenant-scoped; policy di FormRequest
        // memastikan hanya pengelola akademik yang sampai ke sini.
        $event = $this->events->update($academicEvent, $request->validated(), $request->user());

        return redirect()->route('academic-events.index')
            ->with('status', "Kegiatan {$event->title} diperbarui.");
    }

    public function destroy(AcademicEvent $academicEvent, Request $request): RedirectResponse
    {
        $this->authorize('delete', $academicEvent);

        $this->events->delete($academicEvent, $request->user());

        return redirect()->route('academic-events.index')
            ->with('status', 'Kegiatan dihapus.');
    }
}
