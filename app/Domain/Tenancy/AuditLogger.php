<?php

namespace App\Domain\Tenancy;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Append an audit event without persisting secrets or raw PII payloads.
     *
     * @param  array<string, mixed>  $metadata  must not contain passwords, tokens, or full personal data
     */
    public function record(
        string $action,
        ?School $school = null,
        ?User $actor = null,
        ?Model $subject = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'school_id' => $school?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata_json' => $metadata === [] ? null : $metadata,
            'ip_hash' => $this->hashIp(Request::ip()),
        ]);
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
