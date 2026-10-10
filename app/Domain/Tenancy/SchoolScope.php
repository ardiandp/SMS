<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains tenant-owned models to the resolved school.
 * When no school context exists (console, seeders) the scope does not filter,
 * so every HTTP entry point must run inside ResolveSchoolContext.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $schoolId = app(CurrentSchool::class)->school()?->getKey();

        if ($schoolId !== null) {
            $builder->where($model->qualifyColumn('school_id'), $schoolId);
        }
    }
}