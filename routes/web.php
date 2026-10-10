<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AssessmentCategoryController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AttendanceExcuseController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinalGradeController;
use App\Http\Controllers\GradingPolicyController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\SchoolSwitchController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\TeachingAssignmentController;
use App\Http\Middleware\ResolveSchoolContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/schools', [SchoolSwitchController::class, 'select'])->name('schools.select');
    Route::post('/schools/{school}/switch', [SchoolSwitchController::class, 'switch'])->name('schools.switch');

    Route::middleware(ResolveSchoolContext::class)->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Harus sebelum Route::resource('students') agar tidak tertangkap {student}.
        Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
        Route::post('/students/import', [StudentImportController::class, 'preview'])->name('students.import.preview');
        Route::post('/students/import/commit', [StudentImportController::class, 'commit'])->name('students.import.commit');
        Route::post('/students/import/cancel', [StudentImportController::class, 'cancel'])->name('students.import.cancel');

        Route::resource('students', StudentController::class);
        Route::post('/students/{student}/guardians', [StudentController::class, 'linkGuardian'])
            ->name('students.guardians.link');

        Route::post('/students/{student}/documents', [StudentDocumentController::class, 'store'])
            ->name('students.documents.store');
        Route::get('/documents/{document}/download', [StudentDocumentController::class, 'download'])
            ->name('documents.download');

        Route::resource('guardians', GuardianController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('employees', EmployeeController::class);

        // Struktur akademik (PRD-02).
        Route::resource('classrooms', ClassroomController::class)->except(['show']);

        Route::get('academics/grade-levels', [ReferenceDataController::class, 'gradeLevels'])
            ->name('academics.grade-levels.index');
        Route::post('academics/grade-levels', [ReferenceDataController::class, 'storeGradeLevel'])
            ->name('academics.grade-levels.store');
        Route::put('academics/grade-levels/{gradeLevel}', [ReferenceDataController::class, 'updateGradeLevel'])
            ->name('academics.grade-levels.update');

        Route::get('academics/subjects', [ReferenceDataController::class, 'subjects'])
            ->name('academics.subjects.index');
        Route::post('academics/subjects', [ReferenceDataController::class, 'storeSubject'])
            ->name('academics.subjects.store');
        Route::put('academics/subjects/{subject}', [ReferenceDataController::class, 'updateSubject'])
            ->name('academics.subjects.update');

        Route::get('academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::get('academic-years/create', [AcademicYearController::class, 'create'])->name('academic-years.create');
        Route::post('academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
        Route::get('academic-years/{academicYear}', [AcademicYearController::class, 'show'])->name('academic-years.show');
        Route::get('academic-years/{academicYear}/edit', [AcademicYearController::class, 'edit'])->name('academic-years.edit');
        Route::put('academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
        Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
        Route::post('academic-years/{academicYear}/deactivate', [AcademicYearController::class, 'deactivate'])->name('academic-years.deactivate');
        Route::post('academic-years/{academicYear}/semesters', [AcademicYearController::class, 'storeSemester'])->name('academic-years.semesters.store');

        Route::get('study-groups', [StudyGroupController::class, 'index'])->name('study-groups.index');
        Route::get('study-groups/create', [StudyGroupController::class, 'create'])->name('study-groups.create');
        Route::post('study-groups', [StudyGroupController::class, 'store'])->name('study-groups.store');
        Route::get('study-groups/{studyGroup}', [StudyGroupController::class, 'show'])->name('study-groups.show');
        Route::get('study-groups/{studyGroup}/edit', [StudyGroupController::class, 'edit'])->name('study-groups.edit');
        Route::put('study-groups/{studyGroup}', [StudyGroupController::class, 'update'])->name('study-groups.update');
        Route::post('study-groups/{studyGroup}/students', [StudyGroupController::class, 'addStudent'])->name('study-groups.students.store');
        Route::delete('study-groups/{studyGroup}/students/{student}', [StudyGroupController::class, 'removeStudent'])->name('study-groups.students.destroy');

        Route::get('study-groups/{studyGroup}/schedules', [ClassScheduleController::class, 'index'])->name('study-groups.schedules.index');
        Route::post('study-groups/{studyGroup}/schedules', [ClassScheduleController::class, 'store'])->name('study-groups.schedules.store');
        Route::put('schedules/{classSchedule}', [ClassScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{classSchedule}', [ClassScheduleController::class, 'destroy'])->name('schedules.destroy');

        Route::get('teaching-assignments', [TeachingAssignmentController::class, 'index'])->name('teaching-assignments.index');
        Route::post('teaching-assignments', [TeachingAssignmentController::class, 'store'])->name('teaching-assignments.store');
        Route::put('teaching-assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'update'])->name('teaching-assignments.update');
        Route::delete('teaching-assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'destroy'])->name('teaching-assignments.destroy');

        // Absensi (PRD-03).
        Route::get('attendance/sessions', [AttendanceSessionController::class, 'index'])->name('attendance.sessions.index');
        Route::post('attendance/sessions', [AttendanceSessionController::class, 'store'])->name('attendance.sessions.store');
        Route::get('attendance/sessions/roster', [AttendanceSessionController::class, 'roster'])->name('attendance.sessions.roster');
        Route::get('attendance/sessions/{attendanceSession}', [AttendanceSessionController::class, 'show'])->name('attendance.sessions.show');
        Route::post('attendance/sessions/{attendanceSession}/mark', [AttendanceSessionController::class, 'mark'])->name('attendance.sessions.mark');
        Route::post('attendance/sessions/{attendanceSession}/mark-all', [AttendanceSessionController::class, 'markAll'])->name('attendance.sessions.mark-all');
        Route::post('attendance/sessions/{attendanceSession}/finalize', [AttendanceSessionController::class, 'finalize'])->name('attendance.sessions.finalize');
        Route::post('attendance/sessions/{attendanceSession}/reopen', [AttendanceSessionController::class, 'reopen'])->name('attendance.sessions.reopen');
        Route::post('attendance/sessions/{attendanceSession}/students', [AttendanceRecordController::class, 'attach'])->name('attendance.sessions.students.store');

        Route::put('attendance/records/{attendanceRecord}/correct', [AttendanceRecordController::class, 'correct'])->name('attendance.records.correct');
        Route::get('attendance/records/student/{student}', [AttendanceRecordController::class, 'studentSummary'])->name('attendance.records.student');
        Route::get('attendance/records/group/{studyGroup}', [AttendanceRecordController::class, 'studyGroupSummary'])->name('attendance.records.group');

        Route::get('attendance/excuses', [AttendanceExcuseController::class, 'index'])->name('attendance.excuses.index');
        Route::post('attendance/records/{attendanceRecord}/excuse', [AttendanceExcuseController::class, 'store'])->name('attendance.excuses.store');
        Route::post('attendance/excuses/{attendanceExcuse}/review', [AttendanceExcuseController::class, 'review'])->name('attendance.excuses.review');
        Route::get('attendance/excuses/{attendanceExcuse}/download', [AttendanceExcuseController::class, 'download'])->name('attendance.excuses.download');

        Route::get('attendance/reports', [AttendanceReportController::class, 'index'])->name('attendance.reports.index');
        Route::get('attendance/reports/export', [AttendanceReportController::class, 'export'])->name('attendance.reports.export');

        // Penilaian (PRD-04).
        Route::get('assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::post('assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
        Route::put('assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
        Route::delete('assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
        Route::post('assessments/{assessment}/scores', [AssessmentController::class, 'saveScores'])->name('assessments.scores.save');
        Route::post('assessments/{assessment}/publish', [AssessmentController::class, 'publish'])->name('assessments.publish');
        Route::put('assessment-scores/{assessmentScore}/correct', [AssessmentController::class, 'correct'])->name('assessment-scores.correct');

        Route::get('assessment-categories', [AssessmentCategoryController::class, 'index'])->name('assessments.categories.index');
        Route::post('assessment-categories', [AssessmentCategoryController::class, 'store'])->name('assessments.categories.store');
        Route::put('assessment-categories/{assessmentCategory}', [AssessmentCategoryController::class, 'update'])->name('assessments.categories.update');

        // Kebijakan penilaian & nilai akhir (PRD-04).
        Route::get('grading-policies', [GradingPolicyController::class, 'index'])->name('grading-policies.index');
        Route::post('grading-policies', [GradingPolicyController::class, 'store'])->name('grading-policies.store');
        Route::post('grading-policies/{gradingPolicy}/activate', [GradingPolicyController::class, 'activate'])->name('grading-policies.activate');

        Route::get('final-grades', [FinalGradeController::class, 'index'])->name('final-grades.index');
        Route::post('teaching-assignments/{teachingAssignment}/final-grades/calculate', [FinalGradeController::class, 'calculate'])->name('final-grades.calculate');
        Route::post('final-grades/{finalGrade}/finalize', [FinalGradeController::class, 'finalize'])->name('final-grades.finalize');
        Route::put('final-grades/{finalGrade}/correct', [FinalGradeController::class, 'correct'])->name('final-grades.correct');
    });
});
