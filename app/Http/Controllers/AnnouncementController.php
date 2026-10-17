<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AnnouncementService;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('administration.announcements', [
            'announcements' => Announcement::with('recipients')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $announcement = $this->announcements->createDraft(
            $request->validated(),
            $request->validated()['audiences'],
            $request->user(),
        );

        return redirect()->route('announcements.index')
            ->with('status', "Draf {$announcement->title} tersimpan.");
    }

    public function update(StoreAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $updated = $this->announcements->updateDraft(
            $announcement,
            $request->validated(),
            $request->validated()['audiences'],
            $request->user(),
        );

        return redirect()->route('announcements.index')
            ->with('status', "Draf {$updated->title} diperbarui.");
    }

    public function publish(Announcement $announcement): RedirectResponse
    {
        $this->authorize('publish', $announcement);

        $published = $this->announcements->publish($announcement, request()->user());

        return redirect()->route('announcements.index')
            ->with('status', "Pengumuman {$published->title} terbit.");
    }

    public function archive(Announcement $announcement): RedirectResponse
    {
        $this->authorize('archive', $announcement);

        $archived = $this->announcements->archive($announcement, request()->user());

        return redirect()->route('announcements.index')
            ->with('status', "Pengumuman {$archived->title} diarsipkan.");
    }
}
