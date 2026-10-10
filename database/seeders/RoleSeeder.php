<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Baseline roles/permissions for the platform foundation (PRD-00).
     * Roles are global; per-school assignment happens through the teams feature.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'super-admin' => [
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
            ],
            'school-admin' => [
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
            ],
            'staff' => [
                'view.dashboard',
                'view.students',
                'view.employees',
                'manage.documents',
            ],
            'teacher' => [
                'view.dashboard',
                'view.students',
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
