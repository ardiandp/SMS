<?php

namespace App\Notifications;

use App\Models\SchoolLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan penerbitan surat sekolah (PRD-05).
 *
 * Payload hanya memuat nomor, subjek, dan tautan — tanpa body_snapshot
 * isi surat — agar notifikasi tidak menjadi jalur bocor data.
 */
class LetterIssued extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SchoolLetter $letter,
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
            'title' => 'Surat terbit: '.$this->letter->letter_number.' — '.$this->letter->subject,
            'link' => route('school_letters.index'),
        ];
    }
}
