# ERD — IRNIS School (konseptual)

Diagram ini menunjukkan relasi inti. Ini bukan keseluruhan schema final; tabel modul lanjutan akan ditambahkan sesuai PRD.

```mermaid
erDiagram
    SCHOOLS ||--o{ SCHOOL_USER_MEMBERSHIPS : has
    USERS ||--o{ SCHOOL_USER_MEMBERSHIPS : joins
    SCHOOLS ||--o{ STUDENTS : owns
    SCHOOLS ||--o{ GUARDIANS : owns
    STUDENTS ||--o{ STUDENT_GUARDIANS : linked
    GUARDIANS ||--o{ STUDENT_GUARDIANS : linked
    SCHOOLS ||--o{ EMPLOYEES : owns
    USERS o|--o| STUDENT_PROFILES : maps
    USERS o|--o| EMPLOYEE_PROFILES : maps
    SCHOOLS ||--o{ ACADEMIC_YEARS : defines
    ACADEMIC_YEARS ||--o{ SEMESTERS : contains
    SCHOOLS ||--o{ GRADE_LEVELS : defines
    SCHOOLS ||--o{ CLASSROOMS : defines
    ACADEMIC_YEARS ||--o{ STUDY_GROUPS : organizes
    GRADE_LEVELS ||--o{ STUDY_GROUPS : classifies
    STUDY_GROUPS ||--o{ STUDY_GROUP_MEMBERS : includes
    STUDENTS ||--o{ STUDY_GROUP_MEMBERS : enrolled
    SCHOOLS ||--o{ SUBJECTS : defines
    STUDY_GROUPS ||--o{ TEACHING_ASSIGNMENTS : has
    SUBJECTS ||--o{ TEACHING_ASSIGNMENTS : taught
    EMPLOYEES ||--o{ TEACHING_ASSIGNMENTS : assigned
    TEACHING_ASSIGNMENTS ||--o{ CLASS_SCHEDULES : scheduled
    STUDY_GROUPS ||--o{ ATTENDANCE_SESSIONS : holds
    ATTENDANCE_SESSIONS ||--o{ ATTENDANCE_RECORDS : records
    STUDENTS ||--o{ ATTENDANCE_RECORDS : attends
    TEACHING_ASSIGNMENTS ||--o{ ASSESSMENTS : assesses
    ASSESSMENTS ||--o{ ASSESSMENT_SCORES : has
    STUDENTS ||--o{ ASSESSMENT_SCORES : receives
    STUDENTS ||--o{ FINAL_GRADES : earns
    STUDENTS ||--o{ REPORT_CARDS : receives
    REPORT_CARDS ||--o{ REPORT_CARD_ITEMS : contains
    STUDENTS ||--o{ PROMOTION_DECISIONS : evaluated
    SCHOOLS ||--o{ ANNOUNCEMENTS : publishes
    SCHOOLS ||--o{ SCHOOL_LETTERS : issues
    USERS ||--o{ AUDIT_LOGS : performs
```

## Important modeling notes
- A student can have multiple guardians and a guardian can be linked to multiple students; use `student_guardians`.
- Student class placement is historical and year-scoped through `study_group_members`; do not store a single permanent `class_id` on `students`.
- Teacher assignment is distinct from employee identity and is scoped to a school, study group, subject, and academic year.
- A final grade/report card should preserve the published result and publication metadata. Corrections must be audited.
- Use consistent naming conventions; the ERD labels are conceptual names, not necessarily final Laravel table names.
