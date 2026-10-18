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
     * Assessment and report card permissions (PRD-04).
     *
     * Mirrors the attendance split: entering a draft score is a routine act,
     * finalizing is administrative, and correcting an already-finalized number
     * or publishing a report card is the strongest act of all — each gets its
     * own permission so the school can grant them independently.
     */
    private const ASSESSMENT_PERMISSIONS = [
        'view.grades',
        'manage.assessments',
        'manage.grading-policies',
        'finalize.grades',
        'correct.grades',
        'publish.report-cards',
        'export.report-cards',
    ];

    /**
     * Promotion and graduation permissions (PRD-04).
     *
     * Recording who moves up a grade, stays back, or graduates is an
     * administrative act that changes a student's academic path; issuing the
     * graduation document is stronger still. Each gets its own permission.
     */
    private const PROMOTION_PERMISSIONS = [
        'manage.promotions',
        'issue.graduations',
    ];

    /**
     * Dashboard and communication permissions (PRD-05).
     *
     * Publishing a school-wide announcement speaks for the school, so it is
     * separate from reading the dashboard: teachers and staff see
     * announcements, but only announcement managers can draft/publish them.
     */
    private const COMMUNICATION_PERMISSIONS = [
        'manage.announcements',
        'manage.templates',
        'manage.letters',
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
                self::ASSESSMENT_PERMISSIONS,
                self::PROMOTION_PERMISSIONS,
                self::COMMUNICATION_PERMISSIONS,
            ),
            'school-admin' => array_merge(
                self::ADMIN_PERMISSIONS,
                self::ACADEMIC_PERMISSIONS,
                self::ATTENDANCE_PERMISSIONS,
                self::ASSESSMENT_PERMISSIONS,
                self::PROMOTION_PERMISSIONS,
                self::COMMUNICATION_PERMISSIONS,
            ),
            'staff' => [
                'view.dashboard',
                'view.students',
                'view.employees',
                'view.academics',
                'manage.documents',
                'view.attendance',
                'view.grades',
            ],
            'teacher' => [
                'view.dashboard',
                'view.students',
                'view.academics',
                // Guru dapat mengisi absensi, tetapi hanya untuk rombel yang
                // diampu dan hanya correcting sesi yang belum selesai.
                'view.attendance',
                // Guru mengelola asesmen dan menilai untuk assignment yang
                // diampu; finalisasi/koreksi nilai tetap wewenang admin.
                'view.grades',
                'manage.assessments',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            // DatabaseSeeder memakai WithoutModelEvents, sehingga flush cache
            // otomatis dari Spatie (event `created` pada Permission) tidak
            // pernah terpicu saat `migrate:fresh --seed`. Tanpa flush manual
            // ini, syncPermissions di bawah membaca cache registrar yang masih
            // menyimpan daftar permission kosong dan gagal mencari permission
            // yang baru dibuat.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
