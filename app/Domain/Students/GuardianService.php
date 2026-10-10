<?php

namespace App\Domain\Students;

use App\Domain\Tenancy\AuditLogger;
use App\Domain\Tenancy\CurrentSchool;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuardianService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Guardian
    {
        return DB::transaction(function () use ($data, $actor): Guardian {
            $guardian = new Guardian($data);
            $guardian->assignToCurrentSchool();
            $guardian->save();

            $this->audit->record(
                action: 'guardian.created',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $guardian,
                metadata: ['guardian_id' => $guardian->getKey()],
            );

            return $guardian;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Guardian $guardian, array $data, User $actor): Guardian
    {
        return DB::transaction(function () use ($guardian, $data, $actor): Guardian {
            $guardian->fill($data);
            $guardian->save();

            $this->audit->record(
                action: 'guardian.updated',
                school: $this->currentSchool->school(),
                actor: $actor,
                subject: $guardian,
                metadata: ['guardian_id' => $guardian->getKey()],
            );

            return $guardian;
        });
    }
}
