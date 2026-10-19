<?php

namespace App\Http\Controllers;

use App\Domain\Portal\PortalAccess;
use App\Enums\FinalGradeStatus;
use App\Enums\PlacementStatus;
use App\Enums\ReportCardStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassSchedule;
use App\Models\FinalGrade;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudyGroupMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Portal siswa & orang tua (PRD-06).
 *
 * Setiap aksi yang menerima student id memanggil PortalAccess::allowStudent()
 * lebih dulu — ID dari URL tidak pernah dipercaya begitu saja (anti-IDOR).
 * Yang ditampilkan hanya data yang sudah final/published:
 * - nilai: hanya FinalGrade berstatus finalized (draft disembunyikan);
 * - rapor: hanya ReportCard berstatus published.
 */
class PortalController extends Controller implements HasMiddleware
{
    /**
     * Gerbang pertama: hanya pemegang portal.access (role student/parent).
     * Baris data apa pun tetap diputus per-request oleh PortalAccess.
     *
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('can:portal.access')];
    }

    public function __construct(private readonly PortalAccess $access) {}

    /**
     * Beranda portal: daftar profil yang bisa diakses + pilihan profil aktif.
     * Wali multi-anak berpindah profil lewat query ?student= — setiap nilai
     * dilewatkan allowStudent() sehingga profil anak orang lain ditolak 403.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $students = $this->access->visibleStudents($user);
        $active = $this->activeStudent($request, $students);

        return view('portal.index', [
            'students' => $students,
            'activeStudent' => $active,
        ]);
    }

    public function schedule(Request $request, int $student): View
    {
        $this->access->allowStudent($request->user(), $student);
        $profile = Student::query()->findOrFail($student);

        return view('portal.schedule', [
            'student' => $profile,
            'students' => $this->access->visibleStudents($request->user()),
            'schedules' => $this->schedulesFor($student),
        ]);
    }

    public function attendance(Request $request, int $student): View
    {
        $this->access->allowStudent($request->user(), $student);
        $profile = Student::query()->findOrFail($student);

        $records = AttendanceRecord::query()
            ->where('student_id', $profile->getKey())
            ->with(['session.studyGroup', 'excuse'])
            ->orderByDesc('marked_at')
            ->paginate(20);

        return view('portal.attendance', [
            'student' => $profile,
            'students' => $this->access->visibleStudents($request->user()),
            'records' => $records,
        ]);
    }

    public function grades(Request $request, int $student): View
    {
        $this->access->allowStudent($request->user(), $student);
        $profile = Student::query()->findOrFail($student);

        // Hanya nilai yang sudah difinalisasi; draft tidak tampil.
        $grades = FinalGrade::query()
            ->where('student_id', $profile->getKey())
            ->where('status', FinalGradeStatus::Finalized)
            ->with(['teachingAssignment.subject', 'semester.academicYear'])
            ->orderByDesc('finalized_at')
            ->paginate(20);

        return view('portal.grades', [
            'student' => $profile,
            'students' => $this->access->visibleStudents($request->user()),
            'grades' => $grades,
        ]);
    }

    public function reportCards(Request $request, int $student): View
    {
        $this->access->allowStudent($request->user(), $student);
        $profile = Student::query()->findOrFail($student);

        // Hanya rapor yang sudah diterbitkan; draft tidak tampil.
        $cards = ReportCard::query()
            ->where('student_id', $profile->getKey())
            ->where('status', ReportCardStatus::Published)
            ->with(['semester.academicYear', 'items'])
            ->orderByDesc('published_at')
            ->paginate(10);

        return view('portal.report-cards', [
            'student' => $profile,
            'students' => $this->access->visibleStudents($request->user()),
            'cards' => $cards,
        ]);
    }

    public function announcements(Request $request, int $student): View
    {
        $this->access->allowStudent($request->user(), $student);
        $profile = Student::query()->findOrFail($student);

        $announcements = app(\App\Domain\Administration\AnnouncementService::class)
            ->visibleTo($request->user()->getRoleNames()->all(), 20);

        return view('portal.announcements', [
            'student' => $profile,
            'students' => $this->access->visibleStudents($request->user()),
            'announcements' => $announcements,
        ]);
    }

    /**
     * Profil aktif: query ?student= bila valid, jika tidak profil pertama.
     * Nilai query yang tidak terotorisasi → 403 (bukan fallback diam-diam,
     * agar upaya IDOR terlihat sebagai penolakan, bukan data orang lain).
     *
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     */
    private function activeStudent(Request $request, $students): ?Student
    {
        $requested = $request->integer('student');

        if ($requested) {
            $this->access->allowStudent($request->user(), $requested);

            return $students->firstWhere('id', $requested);
        }

        return $students->first();
    }

    /**
     * Jadwal mingguan dari rombel aktif siswa (penempatan status active).
     *
     * @return \Illuminate\Support\Collection<int, ClassSchedule>
     */
    private function schedulesFor(int $studentId)
    {
        $groupIds = StudyGroupMember::query()
            ->where('student_id', $studentId)
            ->where('status', PlacementStatus::Active)
            ->pluck('study_group_id')
            ->all();

        if ($groupIds === []) {
            return collect();
        }

        return ClassSchedule::query()
            ->whereHas('teachingAssignment', fn ($q) => $q->whereIn('study_group_id', $groupIds))
            ->with(['teachingAssignment.subject', 'teachingAssignment.studyGroup'])
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();
    }
}
