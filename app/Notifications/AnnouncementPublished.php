<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan pengumuman sekolah baru terbit (PRD-05).
 *
 * Payload sengaja minimal — hanya judul dan tautan halaman, tanpa isi
 * pengumuman — agar notifikasi tidak menjadi jalur bocor data. Penerima
 * membuka isi lewat halaman pengumuman yang berotorisasi.
 */
class AnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Announcement $announcement,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pengumuman baru: '.$this->announcement->title,
            'link' => route('announcements.index'),
        ];
    }
}
