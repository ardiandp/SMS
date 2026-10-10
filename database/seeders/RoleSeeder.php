<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Baseline permissions (PRD-00 and PRD-01).
     */
    private const ADMIN_PERMISSIONS = [
        'view.dashboard',
        'manage.memberships',
        'view.audit-logs',
        'manage.school-settings',
        'view.students',
        'manage.students',
        'import.students',
        'manage.guardians',
        'view.employees',
        'manage.employees',
        'manage.documents',
    ];

    /**
     * Academic structure permissions (PRD-02).
     */
    private const ACADEMIC_PERMISSIONS = [
        'view.academics',
        'manage.academics',
        'manage.schedules',
    ];

    /**
     * Attendance permissions (PRD-03).
     *
     * `correct.attendance` is deliberately separate from `manage.attendance`:
     * rewriting an already-finalized number is a stronger act than taking the
     * register, so the school can grant them independently.
     */
    private const ATTENDANCE_PERMISSIONS = [
        'view.attendance',
        'manage.attendance',
        'correct.attendance',
        'export.attendance',
    ];

    /**
     * Baseline roles/permissions for the platform foundation (PRD-00).
     * Roles are global; per-school assignment happens through the teams feature.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'super-admin' => array_merge(
                self::ADMIN_PERMISSIONS,
                self::ACADEMIC_PERMISSIONS,
                self::ATTENDANCE_PERMISSIONS,
            ),
            'school-admin' => array_merge(
                self::ADMIN_PERMISSIONS,
                self::ACADEMIC_PERMISSIONS,
                self::ATTENDANCE_PERMISSIONS,
            ),
            'staff' => [
                'view.dashboard',
                'view.students',
                'view.employees',
                'view.academics',
                'manage.documents',
                'view.attendance',
            ],
            'teacher' => [
                'view.dashboard',
                'view.students',
                'view.academics',
                // Guru dapat mengisi absensi, tetapi hanya untuk rombel yang
                // diampu dan hanya correcting sesi yang belum selesai.
                'view.attendance',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
