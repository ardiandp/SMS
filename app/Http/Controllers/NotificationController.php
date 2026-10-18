<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifikasi in-app (PRD-05): daftar milik user aktif dan tanda sudah dibaca.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->paginate(20),
            'unreadCount' => $user->notifications()->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        $user = $request->user();

        // Route binding hanya menjamin baris ada (dan ter-scope sekolah lewat
        // SchoolScope); kepemilikan ke user yang sedang login dicek eksplisit
        // agar tidak ada user yang bisa menandai notifikasi milik user lain.
        abort_unless(
            $notification->notifiable_type === $user->getMorphClass()
                && (int) $notification->notifiable_id === (int) $user->getKey(),
            404
        );

        $notification->markAsRead();

        return back()->with('status', 'Notifikasi ditandai sudah dibaca.');
    }
}
