<?php

namespace App\Policies;

use App\Domain\Tenancy\CurrentSchool;
use App\Models\ReportCard;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantRecord;

class ReportCardPolicy
{
    use AuthorizesTenantRecord;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $this->inSchool() && $user->can('view.grades');
    }

    public function view(User $user, ReportCard $reportCard): bool
    {
        return $this->owns($reportCard) && $user->can('view.grades');
    }

    /**
     * Publishing a report card puts grades in front of parents — an act the
     * school reserves for holders of `publish.report-cards`.
     */
    public function publish(User $user): bool
    {
        return $this->inSchool() && $user->can('publish.report-cards');
    }

    /**
     * Printing/downloading the document itself is a separate permission so the
     * school can restrict who may take a report card off the system.
     */
    public function export(User $user, ReportCard $reportCard): bool
    {
        return $this->owns($reportCard) && $user->can('export.report-cards');
    }
}
