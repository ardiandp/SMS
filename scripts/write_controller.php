<?php

$namespace = 'App\Http\Controllers';

$content = '<?php'."

";
$content .= 'namespace App\\Http\\Controllers;' . "

";
$content .= 'use App\\Domain\\Academics\\QuizQuestionService;' . "
";
$content .= 'use App\\Models\\AcademicYear;' . "
";
$content .= 'use App\\Models\\Subject;' . "
";
$content .= 'use App\\Models\\StudyGroup;' . "
";
$content .= 'use App\\Models\\User;' . "
";
$content .= 'use Illuminate\\Contracts\\View\\View;' . "
";
$content .= 'use Illuminate\\Http\\RedirectResponse;' . "
";
$content .= 'use Illuminate\\Http\\Request;' . "
";
$content .= 'use Illuminate\\Support\\Facades\\Auth;' . "

";
$content .= 'class QuizQuestionsController extends Controller' . "
";
$content .= '{' . "
";
$content .= '    public function __construct(' . "
";
$content .= '        private readonly QuizQuestionService $service,' . "
";
$content .= '    ) {' . "
";
$content .= '    }' . "

";
$content .= '    public function index(Request $request): View' . "
";
$content .= '    {' . "
";
$content .= '        $schoolId = $request->input(' . "'school_id'", ', null);' . "
";
$content .= '        $academicYearId = $request->input(' . "'academic_year_id'", ', null);' . "
";
$content .= '        $subjectId = $request->input(' . "'subject_id'", ', null);' . "
";
$content .= "
";
$content .= '        $query = $this->service->getActive($schoolId ?? 0);' . "
";
$content .= "
";
$content .= '        if ($academicYearId) {' . "
";
$content .= '            $query = $query->where(' . "'academic_year_id'", ', $academicYearId);' . "
";
$content .= '        }' . "
";
$content .= "
";
$content .= '        if ($subjectId) {' . "
";
$content .= '            $query = $query->where(' . "'subject_id'", ', $subjectId);' . "
";
$content .= '        }' . "
";
$content .= "
";
$content .= '        $questions = $query->get();' . "
";
$content .= "
";
$content .= '        $academicYears = AcademicYear::active()->orderBy(' . "'name'", ')->get();' . "
";
$content .= '        $subjects = Subject::orderBy(' . "'name'", ')->get();' . "
";
$content .= '        $studyGroups = StudyGroup::orderBy(' . "'name'", ')->get();' . "
";
$content .= "
";
$content .= '        $selectedSchool = $schoolId ?? 0;' . "
";
$content .= '        $selectedAcademicYear = $academicYearId ?? 0;' . "
";
$content .= '        $selectedSubject = $subjectId ?? 0;' . "
";
$content .= "
";
$content .= '        return view(' . "'quiz-questions.index'", '[' . "
";
$content .= "            'questions' => $questions,". "
";
$content .= "            'academicYears' => $academicYears,". "
";
$content .= "            'subjects' => $subjects,". "
";
$content .= "            'studyGroups' => $studyGroups,". "
";
$content .= "            'selectedSchool' => $selectedSchool,". "
";
$content .= "            'selectedAcademicYear' => $selectedAcademicYear,". "
";
$content .= "            'selectedSubject' => $selectedSubject,". "
";
$content .= "            'users' => User::active()->pluck('name', 'id'),". "
";
$content .= '        ]);' . "
";
$content .= '}' . "

";
$content .= '    public function create(Request $request): View' . "
";
$content .= '    {' . "
";
$content .= '        $schoolId = $request->input(' . "'school_id'", ', null);' . "
";
$content .= "
";
$content .= '        $academicYears = AcademicYear::active()->orderBy(' . "'name'", ')->get();' . "
";
$content .= '        $subjects = Subject::orderBy(' . "'name'", ')->get();' . "
";
$content .= '        $studyGroups = StudyGroup::orderBy(' . "'name'", ')->get();' . "
";
$content .= "
";
$content .= '        return view(' . "'quiz-questions.create'", '[' . "
";
$content .= "            'academicYears' => $academicYears,". "
";
$content .= "            'subjects' => $subjects,". "
";
$content .= "            'studyGroups' => $studyGroups,". "
";
$content .= "            'users' => User::active()->pluck('name', 'id'),". "
";
$content .= "            'schoolId' => $schoolId ?? 0,". "
";
$content .= '        ]);' . "
";
$content .= '}' . "

";
$content .= '    public function store(Request $request): RedirectResponse' . "
";
$content .= '    {' . "
";
$content .= '        $data = $request->validate([' . "
";
$content .= "            'school_id' => 'required|integer|exists:schools,id',". "
";
$content .= "            'academic_year_id' => 'required|integer|exists:academic_years,id',". "
";
$content .= "            'subject_id' => 'required|integer|exists:subjects,id',". "
";
$content .= "            'study_group_id' => 'required|integer|exists:study_groups,id',". "
";
$content .= "            'question_text' => 'required|string|max:2000',". "
";
$content .= "            'choices' => 'required|array|min:2',". "
";
$content .= "            'correct_choice_index' => 'required|integer|min:0',". "
";
$content .= "            'difficulty' => 'sometimes|string|in:easy,medium,hard',". "
";
$content .= "            'question_type' => 'sometimes|string|in:multiple_choice,true_false',". "
";
$content .= "            'created_by' => 'sometimes|integer|exists:users,id',". "
";
$content .= '        ]);' . "
";
$content .= "
";
$content .= '        $data[' . "'choices'", '] = is_array($data[' . "'choices'", ']) ? $data[' . "'choices'", '] : explode(', . "''", ', ', $data[' . "'choices'", ']);' . "
";
$content .= '        $data[' . "'created_by'", '] = $data[' . "'created_by'", '] ?? Auth::id();' . "
";
$content .= '        $data[' . "'updated_by'", '] = $data[' . "'created_by'", '];' . "
";
$content .= "
";
$content .= '        $this->service->create($data, $this->getCurrentUser());' . "
";
$content .= "
";
$content .= '        return redirect()->route(' . "'quiz-questions.index'", ')->with(' . "'success'", ', ' . "'Soal berhasil ditambahkan.'". ');' . "
";
$content .= '}' . "

";
$content .= '    public function show(Request $request, int $id): View' . "
";
$content .= '    {' . "
";
$content .= '        $question = $this->service->getActive($request->input(' . "'school_id'", ', 0))' . "
";
$content .= '            ->where(' . "'id'", ', $id)' . "
";
$content .= '            ->firstOrFail();' . "
";
$content .= "
";
$content .= '        return view(' . "'quiz-questions.show'", '[' . "
";
$content .= "            'question' => $question,". "
";
$content .= "            'users' => User::active()->pluck('name', 'id'),". "
";
$content .= '        ]);' . "
";
$content .= '}' . "

";
$content .= '    public function edit(Request $request, int $id): View' . "
";
$content .= '    {' . "
";
$content .= '        $schoolId = $request->input(' . "'school_id'", ', 0);' . "
";
$content .= '        $question = $this->service->getActive($schoolId)' . "
";
$content .= '            ->where(' . "'id'", ', $id)' . "
";
$content .= '            ->firstOrFail();' . "
";
$content .= "
";
$content .= '        $academicYears = AcademicYear::active()->orderBy(' . "'name'", ')->get();' . "
";
$content .= '        $subjects = Subject::orderBy(' . "'name'", ')->get();' . "
";
$content .= '        $studyGroups = StudyGroup::orderBy(' . "'name'", ')->get();' . "
";
$content .= "
";
$content .= '        return view(' . "'quiz-questions.edit'", '[' . "
";
$content .= "            'question' => $question,". "
";
$content .= "            'academicYears' => $academicYears,". "
";
$content .= "            'subjects' => $subjects,". "
";
$content .= "            'studyGroups' => $studyGroups,". "
";
$content .= "            'users' => User::active()->pluck('name', 'id'),". "
";
$content .= "            'schoolId' => $schoolId,". "
";
$content .= '        ]);' . "
";
$content .= '}' . "

";
$content .= '    public function update(Request $request, int $id): RedirectResponse' . "
";
$content .= '    {' . "
";
$content .= '        $data = $request->validate([' . "
";
$content .= "            'school_id' => 'required|integer|exists:schools,id',". "
";
$content .= "            'academic_year_id' => 'required|integer|exists:academic_years,id',". "
";
$content .= "            'subject_id' => 'required|integer|exists:subjects,id',". "
";
$content .= "            'study_group_id' => 'required|integer|exists:study_groups,id',". "
";
$content .= "            'question_text' => 'required|string|max:2000',". "
";
$content .= "            'choices' => 'required|array|min:2',". "
";
$content .= "            'correct_choice_index' => 'required|integer|min:0',". "
";
$content .= "            'difficulty' => 'sometimes|string|in:easy,medium,hard',". "
";
$content .= "            'question_type' => 'sometimes|string|in:multiple_choice,true_false',". "
";
$content .= "            'updated_by' => 'sometimes|integer|exists:users,id',". "
";
$content .= '        ]);' . "
";
$content .= "
";
$content .= '        $data[' . "'choices'", '] = is_array($data[' . "'choices'", ']) ? $data[' . "'choices'", '] : explode(', . "''", ', ', $data[' . "'choices'", ']);' . "
";
$content .= '        $data[' . "'updated_by'", '] = $data[' . "'updated_by'", '] ?? Auth::id();' . "
";
$content .= "
";
$content .= '        $question = $this->service->getActive($data[' . "'school_id'", '])' . "
";
$content .= '            ->where(' . "'id'", ', $id)' . "
";
$content .= '            ->firstOrFail();' . "
";
$content .= "
";
$content .= '        $this->service->update($question, $data, $this->getCurrentUser());' . "
";
$content .= "
";
$content .= '        return redirect()->route(' . "'quiz-questions.index'", ')->with(' . "'success'", ', ' . "'Soal berhasil diperbarui.'". ');' . "
";
$content .= '}' . "

";
$content .= '    public function destroy(Request $request, int $id): RedirectResponse' . "
";
$content .= '    {' . "
";
$content .= '        $question = $this->service->getActive($request->input(' . "'school_id'", ', 0))' . "
";
$content .= '            ->where(' . "'id'", ', $id)' . "
";
$content .= '            ->firstOrFail();' . "
";
$content .= "
";
$content .= '        $this->service->delete($question, $this->getCurrentUser());' . "
";
$content .= "
";
$content .= '        return redirect()->route(' . "'quiz-questions.index'", ')->with(' . "'success'", ', ' . "'Soal berhasil dihapus.'". ');' . "
";
$content .= '}' . "

";
$content .= '    public function restore(Request $request, int $id): RedirectResponse' . "
";
$content .= '    {' . "
";
$content .= '        $question = $this->service->getActive($request->input(' . "'school_id'", ', 0))' . "
";
$content .= '            ->where(' . "'id'", ', $id)' . "
";
$content .= '            ->firstOrFail();' . "
";
$content .= "
";
$content .= '        $this->service->restore($question, $this->getCurrentUser());' . "
";
$content .= "
";
$content .= '        return redirect()->route(' . "'quiz-questions.index'", ')->with(' . "'success'", ', ' . "'Soal berhasil dikembalikan.'". ');' . "
";
$content .= '}' . "

";
$content .= '    private function getCurrentUser(): User' . "
";
$content .= '    {' . "
";
$content .= '        /** @var User $user */' . "
";
$content .= '        $user = auth()->user();' . "
";
$content .= "
";
$content .= '        return $user;' . "
";
$content .= '}' . "
";
$content .= '}' . "
";

file_put_contents('/var/www/html/SMS/app/Http/Controllers/QuizQuestionsController.php', $content);
echo 'Controller written successfully.' . PHP_EOL;
