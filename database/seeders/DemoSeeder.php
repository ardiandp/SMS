<?php

namespace Database\Seeders;

use App\Domain\Academics\PromotionService;
use App\Domain\Academics\TeachingAssignmentService;
use App\Domain\Assessment\AssessmentScoreService;
use App\Domain\Assessment\AssessmentService;
use App\Domain\Assessment\FinalGradeService;
use App\Domain\Assessment\GradingPolicyService;
use App\Domain\Assessment\ReportCardService;
use App\Domain\Attendance\AttendanceRecordService;
use App\Domain\Attendance\AttendanceSessionService;
use App\Domain\Attendance\ExcuseService;
use App\Domain\Attendance\StudyGroupRoster;
use App\Domain\Tenancy\CurrentSchool;
use App\Enums\AttendanceStatus;
use App\Enums\ExcuseStatus;
use App\Enums\GraduationStatus;
use App\Enums\PlacementStatus;
use App\Enums\SchoolLetterStatus;
use App\Enums\PromotionOutcome;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeUserLink;
use App\Models\FinalGrade;
use App\Models\GradeLevel;
use App\Models\GradingPolicy;
use App\Models\GraduationRecord;
use App\Models\Guardian;
use App\Models\LetterTemplate;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolLetter;
use App\Models\SchoolUserMembership;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\StudentStatusHistory;
use App\Models\StudentUserLink;
use App\Models\GuardianUserLink;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Database\Seeders\QuizSeeder;
use Database\Seeders\QuizQuestionSeeder;
use Database\Seeders\QuizSubmissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data dummy lengkap untuk semua tabel (development/testing).
 *
 * Prinsipnya: data harus REALISTIS dan KONSISTEN, bukan baris acak —
 * setiap alur lewat service domain yang sama dengan produksi (absensi, nilai,
 * rapor, promosi) sehingga audit trail dan invariant benar-benar terbentuk.
 *
 * Tidak pernah dijalankan otomatis oleh DatabaseSeeder; panggil eksplisit:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Guard: dilewati di production dan bila sekolah demo sudah ada (idempoten).
 */
class DemoSeeder extends Seeder
{
    /**
     * true = mode hemat (testing): rentang tanggal/iterasi lebih pendek,
     * tetapi cakupan tabelnya sama lengkap.
     */
    private readonly bool $compact;

    public function __construct()
    {
        $this->compact = app()->environment('testing');
    }

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoSeeder tidak dijalankan di production.');

            return;
        }

        if (School::query()->where('slug', 'nusantara-cendekia')->exists()) {
            $this->command?->warn('Data demo sudah ada. Lewati; gunakan migrate:fresh --seed bila ingin ulang dari nol.');

            return;
        }

        (new RoleSeeder)->run();

        $this->seedMainSchool();
        $this->seedSecondSchool();

        // Surat & template surat untuk kedua sekolah (PRD-05).
        $this->seedLetters(School::where('slug', 'nusantara-cendekia')->first(), User::where('email', 'admin@nusantara.test')->first());
        $this->seedLetters(School::where('slug', 'pelita-bangsa')->first(), User::where('email', 'admin@pelita.test')->first());

        app(CurrentSchool::class)->clear();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->reportCounts();
    }

    /**
     * Sekolah utama: SMP Nusantara Cendekia — cakupan penuh semua modul.
     */
    private function seedMainSchool(): void
    {
        $school = School::create([
            'name' => 'SMP Nusantara Cendekia',
            'slug' => 'nusantara-cendekia',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'settings_json' => ['alamat' => 'Jl. Melati No. 17, Bandung', 'telepon' => '(022) 7712345'],
        ]);

        $admin = $this->makeUser('Budi Santoso', 'admin@nusantara.test', 'school-admin', $school, 'Kepala Sekolah');
        $users = $this->makeUserGroup($school);

        // Semua service butuh konteks sekolah aktif (audit + guard tenant).
        app(CurrentSchool::class)->resolve($admin, $school->getKey());

        [$subjects, $teachers] = $this->seedSubjectsAndTeachers($school, $admin, $users);
        [$yearPrev, $yearActive] = $this->seedAcademicYears($school, $admin);
        $this->seedSemesters($school, $yearPrev);
        [$semesterGanjil] = $this->seedSemesters($school, $yearActive);

        $levels = $this->seedGradeLevels($school);
        $classrooms = $this->seedClassrooms($school);

        $cohorts = $this->seedStudents($school, $admin);
        $firstGuardians = $this->seedGuardians($cohorts['all']);
        $this->seedPortalAccounts($school, $cohorts['all'], $firstGuardians);
        $this->seedStudentDocuments($school, $cohorts['all'], $admin);
        $this->seedStatusHistories($school, $cohorts, $admin);

        $groups = $this->seedStudyGroupsAndPlacements(
            $school, $yearPrev, $yearActive, $levels, $classrooms, $cohorts, $teachers,
        );

        $assignments = $this->seedTeachingAndSchedules(
            $school, $groups['current'], $subjects, $teachers, $semesterGanjil, $yearActive, $admin,
        );

        $this->seedAttendance($school, $groups['current'], $admin, $users);

        $this->seedAssessmentAndGrades(
            $school, $assignments, $semesterGanjil, $yearActive, $admin,
        );

        $this->seedPromotionsAndGraduations($school, $yearPrev, $levels, $cohorts, $admin);

        $this->command?->info('Sekolah "SMP Nusantara Cendekia" selesai diisi.');
    }

    /**
     * Akun login demo. Password seragam "password" (sesuai UserFactory).
     */
    private function makeUser(string $name, string $email, string $roleName, School $school, ?string $jobTitle = null): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        // Kolom ini di luar daftar fillable model; diisi langsung agar akun demo
        // langsung terverifikasi dan laporan "login terakhir" terlihat realistis.
        $user->forceFill([
            'email_verified_at' => now(),
            'last_login_at' => fake()->optional(0.7)->dateTimeBetween('-30 days'),
        ])->save();

        SchoolUserMembership::create([
            'school_id' => $school->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
            'joined_at' => now()->subYears(2)->toDateString(),
        ]);

        setPermissionsTeamId($school->getKey());
        $user->assignRole(Role::findByName($roleName, 'web'));
        setPermissionsTeamId(null);

        return $user;
    }

    /**
     * Guru mapel (bisa login sebagai teacher) + staf TU.
     *
     * @return array<string, User>
     */
    private function makeUserGroup(School $school): array
    {
        $guruNames = [
            'guru1' => 'Siti Rahmawati',
            'guru2' => 'Agus Priyanto',
            'guru3' => 'Dewi Lestari',
            'guru4' => 'Hendra Gunawan',
            'guru5' => 'Rina Marlina',
            'guru6' => 'Joko Susilo',
            'guru7' => 'Fatimah Azzahra',
            'guru8' => 'Rizky Pratama',
        ];

        $users = [];

        foreach ($guruNames as $key => $name) {
            $users[$key] = $this->makeUser(
                $name,
                Str::slug($name, '.').'@nusantara.test',
                'teacher',
                $school,
            );
        }

        $users['staf'] = $this->makeUser('Nurul Hidayah', 'staf@nusantara.test', 'staff', $school);

        return $users;
    }

    /**
     * @return array{0: array<int, Subject>, 1: array<string, array{employee: Employee, user: User}>}
     */
    private function seedSubjectsAndTeachers(School $school, User $admin, array $users): array
    {
        $map = [
            ['MTK', 'Matematika', 'guru1'],
            ['BIND', 'Bahasa Indonesia', 'guru2'],
            ['IPA', 'IPA', 'guru3'],
            ['IPS', 'IPS', 'guru4'],
            ['BING', 'Bahasa Inggris', 'guru5'],
            ['PPKN', 'PPKn', 'guru6'],
            ['PAI', 'Pendidikan Agama Islam', 'guru7'],
            ['INF', 'Informatika', 'guru8'],
        ];

        $subjects = [];
        $teachers = [];

        foreach ($map as $index => [$code, $name, $userKey]) {
            $subject = Subject::create([
                'school_id' => $school->getKey(),
                'code' => $code,
                'name' => $name,
                'category' => $code === 'INF' ? 'pilihan' : 'wajib',
                'is_active' => true,
            ]);

            $user = $users[$userKey];

            $employee = Employee::create([
                'school_id' => $school->getKey(),
                'employee_number' => 'G-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'full_name' => $user->name,
                'employment_status' => $index % 3 === 2 ? 'contract' : 'permanent',
                'joined_on' => fake()->dateTimeBetween('-9 years', '-1 year')->format('Y-m-d'),
            ]);

            EmployeeAssignment::create([
                'school_id' => $school->getKey(),
                'employee_id' => $employee->getKey(),
                'role_title' => 'Guru '.$name,
                'starts_on' => $employee->joined_on,
            ]);

            EmployeeUserLink::create([
                'school_id' => $school->getKey(),
                'employee_id' => $employee->getKey(),
                'user_id' => $user->getKey(),
            ]);

            $subjects[] = $subject;
            $teachers[$name] = ['employee' => $employee, 'user' => $user];
        }

        // Kepala sekolah & staf TU juga punya data karyawan (bukan guru mapel).
        foreach ([[$admin, 'Kepala Sekolah', 'G-KS'], [$users['staf'], 'Staf Tata Usaha', 'G-TU']] as [$user, $title, $number]) {
            $employee = Employee::create([
                'school_id' => $school->getKey(),
                'employee_number' => $number,
                'full_name' => $user->name,
                'employment_status' => 'permanent',
                'joined_on' => '2018-07-01',
            ]);

            EmployeeAssignment::create([
                'school_id' => $school->getKey(),
                'employee_id' => $employee->getKey(),
                'role_title' => $title,
                'starts_on' => '2018-07-01',
            ]);

            EmployeeUserLink::create([
                'school_id' => $school->getKey(),
                'employee_id' => $employee->getKey(),
                'user_id' => $user->getKey(),
            ]);
        }

        return [$subjects, $teachers];
    }

    /**
     * @return array{0: AcademicYear, 1: AcademicYear}
     */
    private function seedAcademicYears(School $school, User $admin): array
    {
        $prev = AcademicYear::create([
            'school_id' => $school->getKey(),
            'name' => '2025/2026',
            'starts_on' => '2025-07-14',
            'ends_on' => '2026-06-30',
            'is_active' => false,
        ]);

        $active = AcademicYear::create([
            'school_id' => $school->getKey(),
            'name' => '2026/2027',
            'starts_on' => '2026-07-13',
            'ends_on' => '2027-06-11',
            'is_active' => true,
        ]);

        AuditLog::create([
            'school_id' => $school->getKey(),
            'actor_user_id' => $admin->getKey(),
            'action' => 'academic_year.created',
            'subject_type' => AcademicYear::class,
            'subject_id' => $active->getKey(),
            'metadata_json' => ['academic_year_id' => $active->getKey()],
        ]);

        return [$prev, $active];
    }

    /**
     * @return array{0: Semester, 1: Semester}
     */
    private function seedSemesters(School $school, AcademicYear $year): array
    {
        [$startYear] = explode('/', $year->name);

        $ganjil = Semester::create([
            'school_id' => $school->getKey(),
            'academic_year_id' => $year->getKey(),
            'name' => 'Ganjil',
            'starts_on' => $startYear.'-07-14',
            'ends_on' => $startYear.'-12-18',
            'sequence' => 1,
        ]);

        $genap = Semester::create([
            'school_id' => $school->getKey(),
            'academic_year_id' => $year->getKey(),
            'name' => 'Genap',
            'starts_on' => ($startYear + 1).'-01-04',
            'ends_on' => ($startYear + 1).'-06-11',
            'sequence' => 2,
        ]);

        return [$ganjil, $genap];
    }

    /**
     * @return array<int, GradeLevel>
     */
    private function seedGradeLevels(School $school): array
    {
        $levels = [];

        foreach ([7 => 'Kelas 7', 8 => 'Kelas 8', 9 => 'Kelas 9'] as $sequence => $name) {
            $levels[$sequence] = GradeLevel::create([
                'school_id' => $school->getKey(),
                'code' => 'SMP-'.$sequence,
                'name' => $name,
                'education_level' => 'SMP',
                'sequence' => $sequence,
                'is_active' => true,
            ]);
        }

        return $levels;
    }

    /**
     * @return array<string, Classroom>
     */
    private function seedClassrooms(School $school): array
    {
        $classrooms = [];

        foreach (['Ruang 7A', 'Ruang 7B', 'Ruang 8A', 'Ruang 9A', 'Lab Komputer'] as $index => $name) {
            $classrooms[$name] = Classroom::create([
                'school_id' => $school->getKey(),
                'name' => $name,
                'building' => $index < 4 ? 'Gedung A' : 'Gedung B',
                'capacity' => 36,
                'status' => 'active',
            ]);
        }

        return $classrooms;
    }

    /**
     * Siswa lintas angkatan supaya promosi/kelulusan punya riwayat nyata.
     *
     * @return array{all: array<int, Student>, alumni: array<int, Student>, grade9: array<int, Student>, grade8: array<int, Student>, grade7: array<int, Student>, withdrawn: array<int, Student>}
     */
    private function seedStudents(School $school, User $admin): array
    {
        $make = function (int $count, string $nisPrefix, int $startSeq) use ($school): array {
            $students = [];

            for ($i = 0; $i < $count; $i++) {
                $gender = fake()->randomElement(['male', 'female']);

                $students[] = Student::create([
                    'school_id' => $school->getKey(),
                    'nis' => $nisPrefix.$this->pad($startSeq + $i, 4),
                    'nisn' => fake()->unique()->numerify('0#########'),
                    'full_name' => $this->indonesianName($gender),
                    'birth_date' => fake()->dateTimeBetween('-16 years', '-11 years')->format('Y-m-d'),
                    'gender' => $gender,
                    'status' => StudentStatus::Active,
                ]);
            }

            return $students;
        };

        // Angkatan lulus 2025/2026 (kelas 9 tahun lalu) — akan punya SKL.
        $alumni = $make(12, '2023', 1);
        // Naik 8 -> 9 tahun ini.
        $grade9 = $make(14, '2024', 1);
        // Naik 7 -> 8 tahun ini.
        $grade8 = $make(16, '2025', 1);
        // Siswa baru tahun ajaran berjalan.
        $grade7 = $make(20, '2026', 1);
        // Variasi status: siswa yang keluar di tengah tahun.
        $withdrawn = $make(2, '2026', 900);

        foreach ($withdrawn as $student) {
            $student->forceFill(['status' => StudentStatus::Withdrawn])->save();
        }

        return [
            'all' => [...$alumni, ...$grade9, ...$grade8, ...$grade7, ...$withdrawn],
            'alumni' => $alumni,
            'grade9' => $grade9,
            'grade8' => $grade8,
            'grade7' => $grade7,
            'withdrawn' => $withdrawn,
        ];
    }

    /**
     * @param  array<int, Student>  $students
     *
     * @return array<int, Guardian> wali pertama (ayah) tiap siswa, sejajar indeks.
     */
    private function seedGuardians(array $students): array
    {
        $first = [];

        foreach ($students as $index => $student) {
            $ayah = Guardian::create([
                'school_id' => $student->school_id,
                'full_name' => $this->indonesianName('male'),
                'phone' => '08'.fake()->unique()->numerify('##########'),
                'email' => fake()->unique()->safeEmail(),
                'address' => fake('id_ID')->address(),
            ]);

            $first[] = $ayah;

            StudentGuardian::create([
                'school_id' => $student->school_id,
                'student_id' => $student->getKey(),
                'guardian_id' => $ayah->getKey(),
                'relationship' => 'father',
                'is_primary' => true,
                'can_pickup' => true,
                'receives_notifications' => true,
            ]);

            // Tidak semua siswa punya ibu terdaftar; variasi data biar realistis.
            if ($index % 5 !== 0) {
                $ibu = Guardian::create([
                    'school_id' => $student->school_id,
                    'full_name' => $this->indonesianName('female'),
                    'phone' => '08'.fake()->unique()->numerify('##########'),
                    'email' => fake()->unique()->safeEmail(),
                    'address' => fake('id_ID')->address(),
                ]);

                StudentGuardian::create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->getKey(),
                    'guardian_id' => $ibu->getKey(),
                    'relationship' => 'mother',
                    'is_primary' => false,
                    'can_pickup' => $index % 2 === 0,
                    'receives_notifications' => true,
                ]);
            }
        }

        return $first;
    }

    /**
     * Akun portal demo (PRD-06): satu siswa + satu wali multi-anak.
     *
     * Wali pertama ditempeli anak kedua juga → mensimulasikan wali dengan
     * beberapa anak yang berpindah profil. Password semua: "password".
     *
     * @param  array<int, Student>  $students
     * @param  array<int, Guardian>  $firstGuardians
     */
    private function seedPortalAccounts(School $school, array $students, array $firstGuardians): void
    {
        if ($students === [] || $firstGuardians === []) {
            return;
        }

        $studentAccount = $this->makeUser(
            $students[0]->full_name,
            'siswa@nusantara.test',
            'student',
            $school,
        );

        StudentUserLink::create([
            'school_id' => $school->getKey(),
            'student_id' => $students[0]->getKey(),
            'user_id' => $studentAccount->getKey(),
        ]);

        // Anak kedua ditempel ke wali pertama juga → wali multi-anak.
        if (isset($students[1], $firstGuardians[0])) {
            StudentGuardian::create([
                'school_id' => $school->getKey(),
                'student_id' => $students[1]->getKey(),
                'guardian_id' => $firstGuardians[0]->getKey(),
                'relationship' => 'father',
                'is_primary' => false,
                'can_pickup' => true,
                'receives_notifications' => true,
            ]);
        }

        $parentAccount = $this->makeUser(
            $firstGuardians[0]->full_name,
            'wali@nusantara.test',
            'parent',
            $school,
        );

        GuardianUserLink::create([
            'school_id' => $school->getKey(),
            'guardian_id' => $firstGuardians[0]->getKey(),
            'user_id' => $parentAccount->getKey(),
        ]);

        $this->command?->line('  Akun portal: siswa@nusantara.test & wali@nusantara.test (password "password").');
    }

    /**
     * Dokumen siswa dengan file PDF dummy sungguhan di disk privat,
     * sehingga alur unduh di aplikasi benar-benar bisa dicoba.
     *
     * @param  array<int, Student>  $students
     */
    private function seedStudentDocuments(School $school, array $students, User $admin): void
    {
        $types = ['birth_certificate' => 'Akta Kelahiran', 'family_card' => 'Kartu Keluarga', 'photo' => 'Pas Foto'];

        // Hanya sebagian siswa yang berkasnya sudah lengkap.
        foreach (array_slice($students, 0, 30) as $index => $student) {
            $chosen = $index % 3 === 0
                ? array_keys($types)
                : [array_rand($types)];

            foreach ($chosen as $type) {
                $relative = sprintf(
                    'students/%d/%s_%s.pdf',
                    $student->getKey(),
                    Str::uuid()->toString(),
                    $type,
                );

                Storage::disk('local')->put($relative, $this->dummyPdf($types[$type].' — '.$student->full_name));

                StudentDocument::create([
                    'school_id' => $school->getKey(),
                    'student_id' => $student->getKey(),
                    'document_type' => $type,
                    'storage_path' => $relative,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => Storage::disk('local')->size($relative),
                    'uploaded_by' => $admin->getKey(),
                ]);
            }
        }
    }

    /**
     * Jejak perubahan status siswa (pendaftaran, keluar, dsb).
     *
     * @param  array{all: array<int, Student>, withdrawn: array<int, Student>}  $cohorts
     */
    private function seedStatusHistories(School $school, array $cohorts, User $admin): void
    {
        foreach ($cohorts['all'] as $student) {
            // Tabel ini memakai `changed_at` sebagai CREATED_AT dan tidak
            // fillable; forceFill agar menyesuaikan tanggal pendaftaran siswa.
            (new StudentStatusHistory([
                'school_id' => $school->getKey(),
                'student_id' => $student->getKey(),
                'from_status' => null,
                'to_status' => StudentStatus::Active->value,
                'reason' => 'Pendaftaran siswa baru',
                'changed_by' => $admin->getKey(),
            ]))->forceFill(['changed_at' => $student->created_at])->save();
        }

        foreach ($cohorts['withdrawn'] as $student) {
            (new StudentStatusHistory([
                'school_id' => $school->getKey(),
                'student_id' => $student->getKey(),
                'from_status' => StudentStatus::Active->value,
                'to_status' => StudentStatus::Withdrawn->value,
                'reason' => 'Pindah sekolah ke luar kota',
                'changed_by' => $admin->getKey(),
            ]))->forceFill(['changed_at' => now()->subMonth()])->save();
        }
    }

    /**
     * Rombel dua tahun ajaran + penempatan siswa dengan riwayat
     * (angkatan lama punya placement lama yang sudah ditutup).
     *
     * @param  array{alumni: array<int, Student>, grade9: array<int, Student>, grade8: array<int, Student>, grade7: array<int, Student>, withdrawn: array<int, Student>}  $cohorts
     * @return array{current: array<int, StudyGroup>}
     */
    private function seedStudyGroupsAndPlacements(
        School $school,
        AcademicYear $yearPrev,
        AcademicYear $yearActive,
        array $levels,
        array $classrooms,
        array $cohorts,
        array $teachers,
    ): array {
        $makeGroup = fn (AcademicYear $year, int $grade, string $name, string $roomKey): StudyGroup => StudyGroup::create([
            'school_id' => $school->getKey(),
            'academic_year_id' => $year->getKey(),
            'grade_level_id' => $levels[$grade]->getKey(),
            'classroom_id' => $classrooms[$roomKey]->getKey(),
            'homeroom_employee_id' => $teachers[array_rand($teachers)]['employee']->getKey(),
            'name' => $name,
            'capacity' => 32,
            'status' => 'active',
        ]);

        $place = function (StudyGroup $group, array $students, string $startsOn, ?string $endsOn, PlacementStatus $status) use ($school): void {
            foreach ($students as $student) {
                StudyGroupMember::create([
                    'school_id' => $school->getKey(),
                    'study_group_id' => $group->getKey(),
                    'student_id' => $student->getKey(),
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'status' => $status,
                ]);
            }
        };

        // Tahun ajaran lalu: 7A, 8A, 9A (penempatan ditutup di akhir tahun).
        $prev7A = $makeGroup($yearPrev, 7, '7A', 'Ruang 7A');
        $prev8A = $makeGroup($yearPrev, 8, '8A', 'Ruang 8A');
        $prev9A = $makeGroup($yearPrev, 9, '9A', 'Ruang 9A');

        $place($prev9A, $cohorts['alumni'], '2025-07-14', '2026-06-30', PlacementStatus::Closed);
        $place($prev8A, $cohorts['grade9'], '2025-07-14', '2026-06-30', PlacementStatus::Closed);
        $place($prev7A, $cohorts['grade8'], '2025-07-14', '2026-06-30', PlacementStatus::Closed);

        // Tahun ajaran berjalan: 7A, 7B, 8A, 9A.
        $now7A = $makeGroup($yearActive, 7, '7A', 'Ruang 7A');
        $now7B = $makeGroup($yearActive, 7, '7B', 'Ruang 7B');
        $now8A = $makeGroup($yearActive, 8, '8A', 'Ruang 8A');
        $now9A = $makeGroup($yearActive, 9, '9A', 'Ruang 9A');

        $place($now9A, $cohorts['grade9'], '2026-07-13', null, PlacementStatus::Active);
        $place($now8A, $cohorts['grade8'], '2026-07-13', null, PlacementStatus::Active);

        [$newA, $newB] = array_chunk($cohorts['grade7'], 10);
        $place($now7A, $newA, '2026-07-13', null, PlacementStatus::Active);
        $place($now7B, $newB, '2026-07-13', null, PlacementStatus::Active);

        // Siswa yang keluar: sempat ditempatkan lalu dipindahkan (riwayat tetap ada).
        $place($now7B, $cohorts['withdrawn'], '2026-07-13', now()->subMonth()->toDateString(), PlacementStatus::Moved);

        return ['current' => [$now7A, $now7B, $now8A, $now9A]];
    }

    /**
     * Isi minimum PDF valid agar tombol "unduh" benar-benar berfungsi.
     */
    private function dummyPdf(string $label): string
    {
        $text = sprintf('Dokumen contoh: %s', $label);

        return "%PDF-1.4\n".
            "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n".
            "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n".
            "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n".
            "trailer<</Root 1 0 R>>\n".
            "%%EOF\n".
            "% {$text}\n";
    }

    private function pad(int $number, int $width): string
    {
        return str_pad((string) $number, $width, '0', STR_PAD_LEFT);
    }

    /**
     * Penugasan mengajar per rombel x mapel (semester aktif) + jadwal mingguan.
     * Satu guru mengampu satu mapel di semua rombel; slot jam dibuat unik
     * per rombel supaya tidak ada bentrok guru/ruang.
     *
     * @param  array<int, StudyGroup>  $groups
     * @param  array<int, Subject>  $subjects
     * @param  array<string, array{employee: Employee, user: User}>  $teachers
     * @return array<int, TeachingAssignment>
     */
    private function seedTeachingAndSchedules(
        School $school,
        array $groups,
        array $subjects,
        array $teachers,
        Semester $semester,
        AcademicYear $year,
        User $admin,
    ): array {
        $service = app(TeachingAssignmentService::class);

        // Satu rombel punya 8 mapel: 6 mapel pertama hari Senin-Jumat pukul
        // "jam mulai rombel", 2 mapel sisa (mapel ke-7/8) di slot +2 jam.
        // Dengan begitu tidak ada dua pelajaran di ruang/guru/waktu yang sama.
        $groupStarts = ['07:00', '08:00', '09:15', '10:15'];

        $assignments = [];

        foreach ($groups as $groupIndex => $group) {
            foreach ($subjects as $subjectIndex => $subject) {
                $teacher = $teachers[$subject->name]['employee'];

                $assignment = $service->create([
                    'study_group_id' => $group->getKey(),
                    'subject_id' => $subject->getKey(),
                    'employee_id' => $teacher->getKey(),
                    'semester_id' => $semester->getKey(),
                ], $admin);

                $day = ($subjectIndex % 6) + 1;
                $start = $subjectIndex < 6
                    ? $groupStarts[$groupIndex]
                    : $this->addMinutes($groupStarts[$groupIndex], 120);

                ClassSchedule::create([
                    'school_id' => $school->getKey(),
                    'teaching_assignment_id' => $assignment->getKey(),
                    'day_of_week' => $day,
                    'starts_at' => $start,
                    'ends_at' => $this->addMinutes($start, 45),
                    'effective_from' => $year->starts_on,
                    'effective_until' => null,
                    'room_label' => $group->classroom?->name,
                ]);

                $assignments[] = $assignment;
            }
        }

        return $assignments;
    }

    private function addMinutes(string $hhmm, int $minutes): string
    {
        return Carbon::parse($hhmm)->addMinutes($minutes)->format('H:i:s');
    }

    /**
     * Absensi 10 hari kerja terakhir per rombel lewat service produksi:
     * buat sesi → tandai variasi status → ajukan surat izin/sakit →
     * review → finalisasi → koreksi terkunci (jejak audit penuh).
     *
     * @param  array<int, StudyGroup>  $groups
     * @param  array<string, User>  $users
     */
    private function seedAttendance(School $school, array $groups, User $admin, array $users): void
    {
        $sessions = app(AttendanceSessionService::class);
        $records = app(AttendanceRecordService::class);
        $excuses = app(ExcuseService::class);

        $days = $this->recentWorkdays($this->compact ? 3 : 10);

        foreach ($days as $dayIndex => $date) {
            foreach ($groups as $group) {
                $teacherUser = $group->homeroomTeacher
                    ? EmployeeUserLink::query()
                        ->where('employee_id', $group->homeroom_employee_id)
                        ->with('user')
                        ->first()?->user
                    : null;

                $actor = $teacherUser ?? $users['staf'];

                $session = $sessions->open($group, $date, $actor);

                // mayoritas hadir, sisanya bervariasi.
                $session->records()->orderBy('id')->get()
                    ->each(function ($record, $index) use ($records, $actor, $dayIndex, $session): void {
                        $roll = ($index * 7 + $dayIndex * 13) % 23;

                        $status = match (true) {
                            $roll === 3 => AttendanceStatus::Sick,
                            $roll === 7 => AttendanceStatus::Permitted,
                            $roll === 17 => AttendanceStatus::Absent,
                            default => AttendanceStatus::Present,
                        };

                        if ($status !== AttendanceStatus::Present) {
                            $records->markMany($session, [$record->getKey() => $status], $actor);
                        }
                    });

                // Ajukan surat untuk sebagian siswa sakit/izin + review.
                $session->records()
                    ->whereIn('status', [AttendanceStatus::Sick->value, AttendanceStatus::Permitted->value])
                    ->take(2)
                    ->get()
                    ->each(function ($record) use ($excuses, $actor, $admin, $dayIndex): void {
                        $reason = $record->status === AttendanceStatus::Sick
                            ? 'Sakit demam, dilampirkan surat dokter.'
                            : 'Izin mengikuti lomba tingkat kota.';

                        $document = $dayIndex % 2 === 0
                            ? UploadedFile::fake()->create('surat.pdf', 12, 'application/pdf')
                            : null;

                        $excuses->submit($record, $reason, $actor, $document);

                        $excuses->review(
                            $record->excuse,
                            $dayIndex % 3 === 0 ? ExcuseStatus::Rejected : ExcuseStatus::Approved,
                            $admin,
                            $dayIndex % 3 === 0 ? 'Dokumen tidak terbaca, mohon unggah ulang.' : 'Surat diterima.',
                        );
                    });

                // Hari yang sudah lewat difinalisasi; hari terbaru dibiarkan open.
                if ($dayIndex < count($days) - 2) {
                    $sessions->finalize($session, $admin);

                    // Satu koreksi pada sesi terkunci (mis. lupa mencatat alpa).
                    // Hari pertama selalu termasuk yang difinalisasi di kedua mode.
                    if ($dayIndex === 0) {
                        $toCorrect = $session->records()
                            ->where('status', AttendanceStatus::Present->value)
                            ->orderBy('id')
                            ->first();

                        if ($toCorrect !== null) {
                            $records->correct($toCorrect, AttendanceStatus::Absent, $admin, 'Siswa terkonfirmasi tidak hadir oleh wali kelas.');
                        }
                    }
                }
            }
        }
    }

    /**
     * @return list<string> tanggal YYYY-MM-DD (Senin-Jumat, terbaru di akhir)
     */
    private function recentWorkdays(int $count): array
    {
        $dates = [];
        $cursor = Carbon::today();

        while (count($dates) < $count) {
            if (! in_array($cursor->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
                $dates[] = $cursor->toDateString();
            }

            $cursor = $cursor->subDay();
        }

        return array_reverse($dates);
    }

    /**
     * Penilaian penuh lewat service: kategori → kebijakan berbasis versi →
     * asesmen (draft + diterbitkan + skor) → koreksi skor → hitung & finalisasi
     * nilai akhir → terbitkan rapor (+ revisi).
     *
     * @param  array<int, TeachingAssignment>  $assignments
     */
    private function seedAssessmentAndGrades(
        School $school,
        array $assignments,
        Semester $semesterGanjil,
        AcademicYear $yearActive,
        User $admin,
    ): void {
        $categories = $this->seedAssessmentCategories($school);
        $policy = $this->seedGradingPolicy($yearActive, $categories, $admin);

        $assessments = app(AssessmentService::class);
        $scores = app(AssessmentScoreService::class);
        $finalGrades = app(FinalGradeService::class);
        $reportCards = app(ReportCardService::class);

        $titles = ['Ulangan Bab 1', 'Tugas Praktikum', 'Kuis Tengah Bab'];

        foreach ($assignments as $index => $assignment) {
            $group = $assignment->studyGroup;
            $teacherUser = EmployeeUserLink::query()
                ->where('employee_id', $assignment->employee_id)
                ->with('user')
                ->first()?->user ?? $admin;

            // Tiga asesmen per penugasan: dua diterbitkan, satu tetap draft.
            foreach ($titles as $tier => $title) {
                $categoryNames = ['Ulangan Harian', 'Tugas', 'Ujian Tengah Semester'];
                $category = $categories[$categoryNames[$tier]];

                $assessment = $assessments->create([
                    'teaching_assignment_id' => $assignment->getKey(),
                    'category_id' => $category->getKey(),
                    'title' => $assignment->subject->name.' — '.$title,
                    'assessment_date' => now()->subWeeks(6 - $tier * 2)->toDateString(),
                    'max_score' => 100,
                ], $teacherUser);

                $roster = app(StudyGroupRoster::class)
                    ->studentsOn($group, $assessment->assessment_date->toDateString());

                $payload = [];

                foreach ($roster as $student) {
                    // ~8% nilai belum diisi (realistis: ada yang belum ujian).
                    $value = ($index + $student->getKey() + $tier) % 13 === 0
                        ? null
                        : round(65 + (($student->getKey() * 7 + $tier * 11) % 31) + 0.5, 1);

                    $payload[$student->getKey()] = [
                        'score' => $value,
                        'feedback' => $value === null ? null : ($value >= 85 ? 'Kerja bagus, pertahankan!' : 'Terus berlatih ya.'),
                    ];
                }

                $scores->saveDraftScores($assessment, $payload, $teacherUser);

                if ($tier < 2) {
                    $assessments->publish($assessment, $teacherUser);
                }
            }
        }

        // Koreksi satu skor yang sudah terbit (jejak audit koreksi).
        $published = Assessment::query()
            ->where('status', 'published')
            ->with('scores')
            ->first();

        $scoreToFix = $published?->scores->first(fn ($row): bool => $row->score !== null);

        if ($published !== null && $scoreToFix !== null) {
            $scores->correctScore(
                $scoreToFix,
                min(100, (float) $scoreToFix->score + 5),
                'Koreksi setelah penilaian ulang dengan rubrik.',
                $admin,
            );
        }

        // Nilai akhir dihitung untuk SEMUA penugasan (termasuk yang draft),
        // tetapi finalisasi ditiadakan untuk satu rombel (9A) yang simulasi
        // nilainya masih berjalan — rapor rombel ini memang belum terbit.
        $draftGroupId = $assignments === []
            ? null
            : $assignments[array_key_last($assignments)]->study_group_id;

        foreach ($assignments as $assignment) {
            $finalGrades->calculateForAssignment($assignment, $policy, $admin);

            if ($assignment->study_group_id === $draftGroupId) {
                continue;
            }

            FinalGrade::query()
                ->where('teaching_assignment_id', $assignment->getKey())
                ->whereNotNull('numeric_score')
                ->orderBy('id')
                ->get()
                ->each(fn ($grade) => $finalGrades->finalize($grade, $admin));
        }

        // Terbitkan rapor semester Ganjil. Rombel yang nilainya masih draft
        // (9A) akan dilewati oleh service — persis perilaku produksi.
        foreach ($this->activeStudentsForGrading($school) as $student) {
            try {
                $reportCards->publish($student, $yearActive, $semesterGanjil, $admin);
            } catch (ValidationException) {
                // Belum ada nilai final untuk siswa ini — lewati.
                continue;
            }
        }

        // Dua rapor direvisi untuk menunjukkan histori revisi.
        ReportCard::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->take(2)
            ->get()
            ->each(fn ($card) => $reportCards->publish($card->student, $yearActive, $semesterGanjil, $admin));

        // PRD-07: Data dummy kuis (LMS) — kuis, soal, dan submission siswa.
        $this->call(QuizSeeder::class);
        $this->call(QuizQuestionSeeder::class);
        $this->call(QuizSubmissionSeeder::class);
    }

    /**
     * @param  array<string, AssessmentCategory>  $categories
     */
    private function seedGradingPolicy(AcademicYear $year, array $categories, User $admin): GradingPolicy
    {
        $service = app(GradingPolicyService::class);

        $weights = [
            $categories['Tugas']->getKey() => 30.0,
            $categories['Ulangan Harian']->getKey() => 30.0,
            $categories['Ujian Tengah Semester']->getKey() => 20.0,
            $categories['Ujian Akhir Semester']->getKey() => 20.0,
        ];

        $bands = [
            ['label' => 'A', 'min' => 90],
            ['label' => 'B', 'min' => 80],
            ['label' => 'C', 'min' => 70],
            ['label' => 'D', 'min' => 60],
            ['label' => 'E', 'min' => 0],
        ];

        $policy = $service->createDraft([
            'academic_year_id' => $year->getKey(),
            'name' => 'Kebijakan Penilaian Semester',
            'config' => ['weights' => $weights, 'grade_bands' => $bands, 'rounding' => 2],
        ], $admin);

        return $service->activate($policy, $admin);
    }

    /**
     * @return array<string, AssessmentCategory>
     */
    private function seedAssessmentCategories(School $school): array
    {
        $map = [
            ['Tugas', 'tugas', 30],
            ['Ulangan Harian', 'ulangan-harian', 30],
            ['Ujian Tengah Semester', 'ujian-tengah-semester', 20],
            ['Ujian Akhir Semester', 'ujian-akhir-semester', 20],
        ];

        $categories = [];

        foreach ($map as [$name, $slug, $weight]) {
            $categories[$name] = AssessmentCategory::create([
                'school_id' => $school->getKey(),
                'name' => $name,
                'slug' => $slug,
                'default_weight' => $weight,
                'is_active' => true,
            ]);
        }

        return $categories;
    }

    /**
     * @return array<int, Student>
     */
    private function activeStudentsForGrading(School $school): array
    {
        return Student::query()
            ->where('school_id', $school->getKey())
            ->where('status', StudentStatus::Active->value)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Keputusan promosi tahun lalu + penerbitan SKL angkatan lulusan.
     * Semua lewat PromotionService (satu baris per siswa per tahun + audit).
     *
     * @param  array{alumni: array<int, Student>, grade9: array<int, Student>, grade8: array<int, Student>}  $cohorts
     * @param  array<int, GradeLevel>  $levels
     */
    private function seedPromotionsAndGraduations(
        School $school,
        AcademicYear $yearPrev,
        array $levels,
        array $cohorts,
        User $admin,
    ): void {
        $service = app(PromotionService::class);

        // Naik kelas: angkatan yang sekarang duduk di kelas 8 & 9.
        foreach ($cohorts['grade9'] as $student) {
            $service->recordDecision(
                $student, $yearPrev, PromotionOutcome::Promoted, $levels[8], $levels[9],
                'Memenuhi syarat kenaikan kelas.', $admin,
            );
        }

        foreach ($cohorts['grade8'] as $student) {
            $service->recordDecision(
                $student, $yearPrev, PromotionOutcome::Promoted, $levels[7], $levels[8],
                'Memenuhi syarat kenaikan kelas.', $admin,
            );
        }

        // Kelulusan angkatan alumni + penerbitan nomor SKL.
        foreach ($cohorts['alumni'] as $index => $student) {
            $service->recordDecision(
                $student, $yearPrev, PromotionOutcome::Graduated, $levels[9], null,
                'Lulus setelah ujian nasional.', $admin,
            );

            // Dua alumni masih berstatus pending (proses administrasi lambat).
            if ($index < 10) {
                $service->issueGraduation(
                    $student, $yearPrev, sprintf('SKL/%d/%03d', 2026, $index + 1), $admin,
                );
            } else {
                GraduationRecord::create([
                    'school_id' => $school->getKey(),
                    'student_id' => $student->getKey(),
                    'academic_year_id' => $yearPrev->getKey(),
                    'status' => GraduationStatus::Pending,
                ]);
            }
        }
    }

    /**
     * Nama Indonesia yang cocok gender dan bebas gelar akademik.
     *
     * Faker id_ID `name()`/`firstName()` kadang menyisipkan suffix gelar
     * ("S.Psi", "M.M.", dsb — aneh untuk siswa SMP/SMA), jadi susun manual
     * dari daftar nama depan + nama belakang sesuai gender.
     */
    private function indonesianName(string $gender): string
    {
        $fake = fake('id_ID');
        $gender = $gender === 'female' ? 'female' : 'male';

        // parse() atas format tanpa {{suffix}} => dijamin bebas gelar.
        $first = $fake->parse($gender === 'female' ? '{{firstNameFemale}}' : '{{firstNameMale}}');
        $last = $gender === 'female' ? $fake->lastNameFemale() : $fake->lastNameMale();

        // ~20% punya nama tengah supaya variatif tapi tetap wajar.
        $middle = fake()->boolean(20)
            ? $fake->parse($gender === 'female' ? '{{firstNameFemale}}' : '{{firstNameMale}}').' '
            : '';

        return "{$first} {$middle}{$last}";
    }

    /**
     * Sekolah kedua kecil — untuk menguji isolasi tenant & fitur ganti sekolah.
     */
    private function seedSecondSchool(): void
    {
        $school = School::create([
            'name' => 'SMA Pelita Bangsa',
            'slug' => 'pelita-bangsa',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'settings_json' => null,
        ]);

        $admin = $this->makeUser('Citra Dewi', 'admin@pelita.test', 'school-admin', $school);

        app(CurrentSchool::class)->resolve($admin, $school->getKey());

        $year = AcademicYear::create([
            'school_id' => $school->getKey(),
            'name' => '2026/2027',
            'starts_on' => '2026-07-13',
            'ends_on' => '2027-06-11',
            'is_active' => true,
        ]);

        $this->seedSemesters($school, $year);

        $level = GradeLevel::create([
            'school_id' => $school->getKey(),
            'code' => 'SMA-10',
            'name' => 'Kelas 10',
            'education_level' => 'SMA',
            'sequence' => 10,
            'is_active' => true,
        ]);

        $classroom = Classroom::create([
            'school_id' => $school->getKey(),
            'name' => 'Ruang 10A',
            'building' => 'Gedung Utama',
            'capacity' => 32,
            'status' => 'active',
        ]);

        $teacherUser = $this->makeUser('Andi Wijaya', 'guru@pelita.test', 'teacher', $school);

        $employee = Employee::create([
            'school_id' => $school->getKey(),
            'employee_number' => 'G-001',
            'full_name' => $teacherUser->name,
            'employment_status' => 'permanent',
            'joined_on' => '2022-08-01',
        ]);

        EmployeeUserLink::create([
            'school_id' => $school->getKey(),
            'employee_id' => $employee->getKey(),
            'user_id' => $teacherUser->getKey(),
        ]);

        $subject = Subject::create([
            'school_id' => $school->getKey(),
            'code' => 'MTK',
            'name' => 'Matematika',
            'category' => 'wajib',
            'is_active' => true,
        ]);

        $group = StudyGroup::create([
            'school_id' => $school->getKey(),
            'academic_year_id' => $year->getKey(),
            'grade_level_id' => $level->getKey(),
            'classroom_id' => $classroom->getKey(),
            'homeroom_employee_id' => $employee->getKey(),
            'name' => '10A',
            'capacity' => 32,
            'status' => 'active',
        ]);

        // Enam siswa baru + penempatan aktif di rombel 10A.
        for ($i = 1; $i <= 6; $i++) {
            $gender = fake()->randomElement(['male', 'female']);

            $student = Student::create([
                'school_id' => $school->getKey(),
                'nis' => '2026P'.$this->pad($i, 3),
                'nisn' => fake()->unique()->numerify('0#########'),
                'full_name' => $this->indonesianName($gender),
                'birth_date' => fake()->dateTimeBetween('-16 years', '-15 years')->format('Y-m-d'),
                'gender' => $gender,
                'status' => StudentStatus::Active,
            ]);

            StudyGroupMember::create([
                'school_id' => $school->getKey(),
                'study_group_id' => $group->getKey(),
                'student_id' => $student->getKey(),
                'starts_on' => '2026-07-13',
                'ends_on' => null,
                'status' => PlacementStatus::Active,
            ]);
        }

        $this->command?->info('Sekolah "SMA Pelita Bangsa" selesai diisi.');
    }

    private function seedLetters(School $school, User $admin): void
    {
        app(CurrentSchool::class)->resolve($admin, $school->getKey());

        $templates = [
            [
                'code' => 'SKL-001',
                'name' => 'Surat Keterangan Lulus',
                'body_template' => "Yang bertanda tangan di bawah ini, Kepala Sekolah {{sekolah}}, menerangkan bahwa {{nama}} telah lulus dari {{sekolah}} pada tahun {{tahun}}.<br><br>Demikian surat ini dibuat untuk digunakan sebagaimana mestinya.",
            ],
            [
                'code' => 'SKD-001',
                'name' => 'Surat Keterangan Domisili',
                'body_template' => "Yang bertanda tangan di bawah ini, Kepala Sekolah {{sekolah}}, menerangkan bahwa {{nama}} berdomisili di {{alamat}}.<br><br>Demikian surat ini dibuat untuk digunakan sebagaimana mestinya.",
            ],
            [
                'code' => 'SKS-001',
                'name' => 'Surat Keterangan Sehat',
                'body_template' => "Yang bertanda tangan di bawah ini, Kepala Sekolah {{sekolah}}, menerangkan bahwa {{nama}} dalam keadaan sehat jasmani dan rohani.<br><br>Demikian surat ini dibuat untuk digunakan sebagaimana mestinya.",
            ],
        ];

        foreach ($templates as $templateData) {
            LetterTemplate::factory()->create(array_merge($templateData, [
                'school_id' => $school->getKey(),
                'created_by' => $admin->getKey(),
            ]));
        }

        // Surat draf tanpa template
        SchoolLetter::factory()->count(3)->create([
            'school_id' => $school->getKey(),
            'created_by' => $admin->getKey(),
        ]);

        // Surat terbit dengan template aktif
        $activeTemplate = LetterTemplate::where('school_id', $school->getKey())
            ->where('is_active', true)
            ->first();

        if ($activeTemplate) {
            SchoolLetter::factory()->count(2)->create([
                'school_id' => $school->getKey(),
                'template_id' => $activeTemplate->getKey(),
                'created_by' => $admin->getKey(),
            ]);

            // Dua surat terbit
            SchoolLetter::factory()->count(2)->issued()->create([
                'school_id' => $school->getKey(),
                'template_id' => $activeTemplate->getKey(),
                'created_by' => $admin->getKey(),
            ]);
        }

        $this->command?->line(sprintf('  Surat & template untuk "%s" selesai diisi.', $school->name));
    }

    private function reportCounts(): void
    {
        $tables = [
            'schools', 'users', 'school_user_memberships', 'students', 'guardians',
            'student_guardians', 'student_documents', 'student_status_histories',
            'employees', 'employee_user_links', 'employee_assignments',
            'academic_years', 'semesters', 'grade_levels', 'classrooms', 'subjects',
            'study_groups', 'study_group_members', 'teaching_assignments', 'class_schedules',
            'attendance_sessions', 'attendance_records', 'attendance_excuses',
            'assessment_categories', 'assessments', 'assessment_scores', 'grading_policies',
            'final_grades', 'report_cards', 'report_card_items',
            'promotion_decisions', 'graduation_records', 'audit_logs',
            'letter_templates', 'school_letters',
        ];

        $this->command?->newLine();
        $this->command?->info('Ringkasan data dummy:');

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $this->command?->line(sprintf('  %-28s %5d baris', $table, $count));
        }

        $this->command?->newLine();
        $this->command?->info('Akun demo (password semua: "password"):');
        $this->command?->line('  admin@nusantara.test — Kepala Sekolah (school-admin), SMP Nusantara Cendekia');
        $this->command?->line('  siti.rahmawati@nusantara.test dst (8 guru, role teacher)');
        $this->command?->line('  staf@nusantara.test   — Staf TU (staff)');
        $this->command?->line('  admin@pelita.test     — Admin, SMA Pelita Bangsa (tenant kedua)');
    }
}
