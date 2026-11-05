# Internship Tracking System — Data Dictionary

This is the data dictionary for the **implemented MySQL/MariaDB schema** in
[`../database.sql`](../database.sql), database `internship_tracking_system`.
It documents all 15 application tables and 113 columns in that schema.

> **Source of truth:** `database.sql` is the schema used by the application.
> [`ERD-schema.sql`](./ERD-schema.sql) is a separate proposed/legacy ERD
> schema with different table and column names; its fields are not included
> here. See [`EERD.md`](./EERD.md) for the implemented entity relationships.

## Conventions

- **PK** = primary key; **FK** = foreign key; **UK** = unique constraint;
  **AI** = auto-increment.
- **Required** means `NOT NULL`; **Nullable** means `NULL` is allowed.
- Defaults below are SQL defaults, not example data. “None” means no explicit
  default is declared.
- `TINYINT(1)` values are used as Boolean flags (`1` = yes/active; `0` =
  no/inactive).
- `TIMESTAMP` defaults to the current database timestamp where specified.
  `updated_at` also updates automatically on row modification.
- IDs use `BIGINT UNSIGNED` unless a table's field entry says otherwise.
- All tables use InnoDB. The installation script creates the database with
  `utf8mb4` / `utf8mb4_unicode_ci`.

## Table overview

| Table | Purpose | Primary key |
|---|---|---|
| `users` | Login accounts and role-based access | `id` |
| `password_resets` | Hashed, expiring password-reset tokens | `id` |
| `student_profiles` | Student-specific identity and contact data | `id` |
| `companies` | Internship partner organizations | `id` |
| `supervisors` | Company-side supervisor profile linked to a user | `id` |
| `internship_placements` | Student/company/supervisor assignment and hour target | `id` |
| `requirements` | Configurable documents students must submit | `id` |
| `document_submissions` | Uploaded document metadata and review result | `id` |
| `attendance_records` | Attendance sessions and verification/correction audit | `id` |
| `evaluation_criteria` | Configurable evaluation criteria and weights | `id` |
| `evaluations` | Supervisor feedback for a student placement | `id` |
| `evaluation_scores` | Rating of an evaluation criterion (junction table) | (`evaluation_id`, `criteria_id`) |
| `notifications` | Per-user in-app messages and read state | `id` |
| `announcements` | Coordinator-authored role-audience broadcasts | `id` |
| `audit_logs` | Recorded application actions and request metadata | `id` |

---

## 1. `users`

One authentication account per user. `role` determines the workspace; a
coordinator is represented by a `users` row and does not have a separate
coordinator-profile table.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Internal account identifier. |
| `username` | `VARCHAR(80)` | No | UK | — | Unique sign-in username. |
| `full_name` | `VARCHAR(120)` | No | — | — | Account holder's display name. |
| `email` | `VARCHAR(190)` | No | UK | — | Unique account email address. |
| `avatar` | `VARCHAR(255)` | Yes | — | `NULL` | Stored avatar reference/path; served through the authorized avatar endpoint. |
| `password_hash` | `VARCHAR(255)` | No | — | — | Password hash; never store the plaintext password here. |
| `role` | `ENUM('student','supervisor','coordinator')` | No | — | — | Role discriminator used for authorization and dashboard routing. |
| `status` | `ENUM('active','inactive')` | No | — | `'active'` | Whether the account is enabled for normal use. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Account creation time. |
| `updated_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Time of the most recent row update. |

**Indexes:** `UNIQUE(username)`, `UNIQUE(email)`,
`idx_users_role_status(role, status)`.

## 2. `password_resets`

Short-lived password recovery credentials. The raw reset token is not stored;
the application stores its SHA-256 hash.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Reset-request identifier. |
| `user_id` | `BIGINT UNSIGNED` | No | FK → `users.id` | — | Account requesting a password reset. |
| `token_hash` | `CHAR(64)` | No | UK | — | Hex-encoded SHA-256 token hash used to validate the reset link. |
| `expires_at` | `DATETIME` | No | — | — | Date and time after which the token must no longer be accepted. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Reset-request creation time. |

**Delete behavior:** deleting the referenced user cascades to its reset rows.

## 3. `student_profiles`

Student-only information associated one-to-one with a user account.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Internal student-profile identifier. |
| `user_id` | `BIGINT UNSIGNED` | No | FK, UK → `users.id` | — | Linked login account; unique so one user has at most one student profile. |
| `student_number` | `VARCHAR(50)` | No | UK | — | Institution/programme student identifier. |
| `course` | `VARCHAR(120)` | No | — | `'BS Information Technology'` | Student's programme/course. |
| `year_level` | `VARCHAR(30)` | No | — | `'4th Year'` | Student's year level. |
| `phone` | `VARCHAR(40)` | No | — | `''` | Contact number; blank string when not supplied. |
| `address` | `VARCHAR(255)` | No | — | `''` | Student contact/home address; blank string when not supplied. |
| `attendance_token` | `CHAR(64)` | Yes | UK | `NULL` | Unique attendance QR token associated with the student. Treat as sensitive attendance credential. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Profile creation time. |

**Delete behavior:** deleting the linked user cascades to the student profile.

## 4. `companies`

Partner organizations offering internship placements.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Company identifier. |
| `name` | `VARCHAR(180)` | No | — | — | Organization/company name. |
| `address` | `VARCHAR(255)` | No | — | `''` | Company address. |
| `contact_person` | `VARCHAR(120)` | No | — | `''` | Primary company contact name. |
| `contact_email` | `VARCHAR(190)` | No | — | `''` | Primary company contact email. |
| `contact_phone` | `VARCHAR(40)` | No | — | `''` | Primary company contact telephone. |
| `status` | `ENUM('active','inactive')` | No | — | `'active'` | Whether the company is currently available as a partner. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Company-record creation time. |

**Indexes:** `idx_companies_status(status)`.

## 5. `supervisors`

Company-side supervisor details. The account is in `users`; this table stores
the supervisor's company association and job title.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Supervisor-profile identifier. |
| `user_id` | `BIGINT UNSIGNED` | No | FK, UK → `users.id` | — | Linked supervisor login account; unique per account. |
| `company_id` | `BIGINT UNSIGNED` | Yes | FK → `companies.id` | `NULL` | Associated company; may be unassigned. |
| `job_title` | `VARCHAR(120)` | No | — | `''` | Supervisor's position/job title. |

**Delete behavior:** deleting the user cascades to the supervisor profile;
deleting the company sets `company_id` to `NULL`.

## 6. `internship_placements`

Connects a student to a host company and optional supervisor for a defined
internship period and required number of hours.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Placement identifier. |
| `student_id` | `BIGINT UNSIGNED` | No | FK → `student_profiles.id` | — | Student assigned to the placement. |
| `company_id` | `BIGINT UNSIGNED` | No | FK → `companies.id` | — | Host company. |
| `supervisor_id` | `BIGINT UNSIGNED` | Yes | FK → `supervisors.id` | `NULL` | Assigned company supervisor, when set. |
| `start_date` | `DATE` | No | — | — | Placement start date. |
| `end_date` | `DATE` | Yes | — | `NULL` | Placement end date; `NULL` can represent an open-ended placement. |
| `required_hours` | `DECIMAL(8,2)` | No | — | `486.00` | Required internship hours for this placement. Must be greater than zero. |
| `status` | `ENUM('active','completed','inactive')` | No | — | `'active'` | Placement lifecycle state. |
| `archived` | `TINYINT(1)` | No | — | `0` | Archive flag independent of the status value. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Placement creation time. |

**Indexes:** `idx_placements_student_status(student_id, status)`.
**Constraints:** `chk_placement_required_hours` requires `required_hours > 0`.
The schema comment describes at most one active placement per student, but
there is no unique database index enforcing that rule; application logic must
maintain it.

**Delete behavior:** deleting the student cascades to placements; deleting an
assigned supervisor sets `supervisor_id` to `NULL`; company deletion is
restricted while referenced unless dependent rows are handled first.

## 7. `requirements`

Coordinator-defined documents or milestones students must submit.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Requirement identifier. |
| `title` | `VARCHAR(180)` | No | — | — | Short requirement name. |
| `description` | `TEXT` | Yes | — | `NULL` | Instructions or additional context for the requirement. |
| `due_date` | `DATE` | Yes | — | `NULL` | Submission deadline; `NULL` means no deadline is set. |
| `allowed_extensions` | `VARCHAR(120)` | No | — | `'pdf,jpg,jpeg,png'` | Comma-separated allowed file extensions, validated by the application. |
| `max_file_bytes` | `INT UNSIGNED` | No | — | `5242880` | Maximum accepted upload size in bytes; default is 5 MiB. |
| `active` | `TINYINT(1)` | No | — | `1` | Whether the requirement is available for current submissions. |
| `archived` | `TINYINT(1)` | No | — | `0` | Whether the requirement is archived. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Requirement creation time. |

**Indexes:** `idx_requirements_active(active, archived, due_date)`.

## 8. `document_submissions`

One uploaded document submission for a requirement by a student. Stores
private storage metadata and the review outcome.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Submission identifier. |
| `requirement_id` | `BIGINT UNSIGNED` | No | FK → `requirements.id` | — | Requirement this document satisfies. |
| `student_id` | `BIGINT UNSIGNED` | No | FK → `student_profiles.id` | — | Student who submitted the document. |
| `stored_name` | `VARCHAR(255)` | No | — | — | Application-generated stored filename; not the original client filename. |
| `original_name` | `VARCHAR(255)` | No | — | — | Sanitized original filename shown to authorized users. |
| `mime_type` | `VARCHAR(100)` | No | — | — | Verified file MIME type. |
| `file_size` | `INT UNSIGNED` | No | — | — | Uploaded file length in bytes. |
| `status` | `ENUM('submitted','approved','rejected')` | No | — | `'submitted'` | Current document-review state. |
| `reviewer_id` | `BIGINT UNSIGNED` | Yes | FK → `users.id` | `NULL` | User who reviewed the submission, if assigned. |
| `reviewer_remarks` | `VARCHAR(1000)` | Yes | — | `NULL` | Reviewer feedback; a rejection requires a reason in the application. |
| `submitted_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Submission creation time. |
| `reviewed_at` | `DATETIME` | Yes | — | `NULL` | Time the submission was reviewed. |

**Indexes:** `idx_submissions_review(status, submitted_at)`.
**Delete behavior:** deleting the requirement or student cascades to
submissions; deleting the reviewer sets `reviewer_id` to `NULL`.
Files are stored privately and accessed through authorized application routes.

## 9. `attendance_records`

Attendance time-in/time-out session, rendered hours, verification, and
correction information.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Attendance-session identifier. |
| `placement_id` | `BIGINT UNSIGNED` | No | FK → `internship_placements.id` | — | Internship placement associated with the session. |
| `student_id` | `BIGINT UNSIGNED` | No | FK → `student_profiles.id` | — | Student whose attendance is recorded. |
| `time_in` | `DATETIME` | No | — | — | Start timestamp for the attendance session. |
| `time_out` | `DATETIME` | Yes | — | `NULL` | End timestamp; `NULL` means the session is still open. |
| `rendered_hours` | `DECIMAL(8,2)` | No | — | `0` | Session duration in hours, rounded to two decimal places by the application. Must be non-negative. |
| `status` | `ENUM('pending','approved','rejected')` | No | — | `'pending'` | Verification state; only approved sessions count toward approved-hour totals. |
| `verified_by` | `BIGINT UNSIGNED` | Yes | FK → `users.id` | `NULL` | User who made the verification decision. |
| `verified_at` | `DATETIME` | Yes | — | `NULL` | Time the session was verified. |
| `correction_reason` | `VARCHAR(500)` | Yes | — | `NULL` | Explanation for a coordinator correction to the session. |
| `corrected_by` | `BIGINT UNSIGNED` | Yes | FK → `users.id` | `NULL` | User who corrected the attendance record. |
| `corrected_at` | `DATETIME` | Yes | — | `NULL` | Time the correction was made. |
| `flagged_reason` | `VARCHAR(255)` | Yes | — | `NULL` | Optional reason the attendance record was flagged. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Attendance-row creation time. |

**Indexes:** `idx_attendance_student_date(student_id, time_in)`,
`idx_attendance_status(status)`.
**Constraints:** `chk_attendance_hours` requires `rendered_hours >= 0`.
**Delete behavior:** deleting a placement or student cascades to its attendance
records; deleting a verifier/corrector sets the corresponding user reference
to `NULL`.

**Integrity note:** both `placement_id` and `student_id` are independently
foreign-keyed, but the schema does not enforce that the student belongs to
that placement. Application logic is responsible for keeping the pair
consistent.

## 10. `evaluation_criteria`

Coordinator-configurable dimensions used to score supervisor evaluations.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Evaluation-criterion identifier. |
| `name` | `VARCHAR(120)` | No | UK | — | Unique criterion label. |
| `weight` | `DECIMAL(6,2)` | No | — | `1` | Relative weight used when calculating a weighted evaluation average; must be non-negative. |
| `active` | `TINYINT(1)` | No | — | `1` | Whether supervisors can use the criterion for new evaluations. |

**Constraints:** `chk_criteria_weight` requires `weight >= 0`.

## 11. `evaluations`

Supervisor feedback associated with a student and internship placement.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Evaluation identifier. |
| `placement_id` | `BIGINT UNSIGNED` | No | FK → `internship_placements.id` | — | Placement being evaluated. |
| `student_id` | `BIGINT UNSIGNED` | No | FK → `student_profiles.id` | — | Student receiving the evaluation. |
| `supervisor_id` | `BIGINT UNSIGNED` | No | FK → `supervisors.id` | — | Supervisor who submitted the evaluation. |
| `comments` | `TEXT` | Yes | — | `NULL` | General written feedback from the supervisor. |
| `released` | `TINYINT(1)` | No | — | `0` | Whether the coordinator has released the evaluation to the student. |
| `evaluated_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Evaluation creation/submission time. |

**Indexes:** `idx_evaluations_student(student_id, released)`.
**Delete behavior:** deleting the placement, student, or supervisor cascades
to the evaluation.

**Integrity note:** `placement_id`, `student_id`, and `supervisor_id` are
separately constrained, but the schema does not enforce that all three refer
to the same placement assignment; the application must enforce that rule.

## 12. `evaluation_scores`

Junction table connecting an evaluation with its scored criteria. A composite
primary key permits at most one score for each criterion in an evaluation.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `evaluation_id` | `BIGINT UNSIGNED` | No | PK, FK → `evaluations.id` | — | Evaluation receiving this criterion score. |
| `criteria_id` | `BIGINT UNSIGNED` | No | PK, FK → `evaluation_criteria.id` | — | Criterion used for the score. |
| `rating` | `TINYINT UNSIGNED` | No | — | — | Rating for the criterion, inclusive range 1–5. |

**Primary key:** (`evaluation_id`, `criteria_id`).
**Constraints:** `chk_score_rating` requires `rating BETWEEN 1 AND 5`.
**Delete behavior:** deleting the evaluation or criterion cascades to its
score rows.

## 13. `notifications`

In-app message delivered to a user account.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Notification identifier. |
| `user_id` | `BIGINT UNSIGNED` | No | FK → `users.id` | — | Recipient account. |
| `title` | `VARCHAR(180)` | No | — | — | Short notification heading. |
| `body` | `VARCHAR(1000)` | No | — | `''` | Notification detail/message; may be blank. |
| `read_at` | `DATETIME` | Yes | — | `NULL` | Read timestamp; `NULL` indicates unread. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Notification creation time. |

**Indexes:** `idx_notifications_user_read(user_id, read_at, created_at)`.
**Delete behavior:** deleting the user cascades to their notifications.

## 14. `announcements`

Coordinator-created announcements addressed to a role audience.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Announcement identifier. |
| `title` | `VARCHAR(180)` | No | — | — | Announcement heading. |
| `body` | `TEXT` | No | — | — | Announcement content. |
| `audience` | `ENUM('all','student','supervisor','coordinator')` | No | — | `'all'` | Intended recipient role; `all` means every role. |
| `created_by` | `BIGINT UNSIGNED` | No | FK → `users.id` | — | User account that created the announcement. |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Announcement creation/publication time. |

**Delete behavior:** deleting the creator cascades to announcements authored
by that user. The audience value does not itself create notification rows;
announcement/notification behavior is handled by application logic.

## 15. `audit_logs`

Application activity records, including administrative actions and
attendance corrections.

| Column | SQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | PK, AI | — | Audit-event identifier. |
| `user_id` | `BIGINT UNSIGNED` | Yes | FK → `users.id` | `NULL` | Acting account; nullable for events without a retained user reference. |
| `action` | `VARCHAR(120)` | No | — | — | Application-defined action code/name. |
| `details` | `VARCHAR(1000)` | No | — | `''` | Context/details for the action; application-defined text. |
| `ip_address` | `VARCHAR(45)` | Yes | — | `NULL` | Request IP address in a representation up to 45 characters (supports IPv6 text). |
| `created_at` | `TIMESTAMP` | No | — | `CURRENT_TIMESTAMP` | Time the audit event was recorded. |

**Indexes:** `idx_audit_created(created_at)`.
**Delete behavior:** deleting the user sets `user_id` to `NULL`, preserving
the audit entry.

---

## Relationships and delete behavior

| Parent | Child / FK | Cardinality | On parent delete |
|---|---|---|---|
| `users` | `student_profiles.user_id` | 1 to 0..1 | Cascade |
| `users` | `supervisors.user_id` | 1 to 0..1 | Cascade |
| `users` | `password_resets.user_id` | 1 to many | Cascade |
| `users` | `document_submissions.reviewer_id` | 1 to 0..many | Set reviewer to `NULL` |
| `users` | `attendance_records.verified_by` | 1 to 0..many | Set verifier to `NULL` |
| `users` | `attendance_records.corrected_by` | 1 to 0..many | Set corrector to `NULL` |
| `users` | `notifications.user_id` | 1 to many | Cascade |
| `users` | `announcements.created_by` | 1 to many | Cascade |
| `users` | `audit_logs.user_id` | 1 to 0..many | Set actor to `NULL` |
| `companies` | `supervisors.company_id` | 1 to 0..many | Set company to `NULL` |
| `companies` | `internship_placements.company_id` | 1 to many | Restrict (default InnoDB FK behavior) |
| `student_profiles` | `internship_placements.student_id` | 1 to many | Cascade |
| `supervisors` | `internship_placements.supervisor_id` | 1 to 0..many | Set supervisor to `NULL` |
| `requirements` | `document_submissions.requirement_id` | 1 to many | Cascade |
| `student_profiles` | `document_submissions.student_id` | 1 to many | Cascade |
| `internship_placements` | `attendance_records.placement_id` | 1 to many | Cascade |
| `student_profiles` | `attendance_records.student_id` | 1 to many | Cascade |
| `internship_placements` | `evaluations.placement_id` | 1 to many | Cascade |
| `student_profiles` | `evaluations.student_id` | 1 to many | Cascade |
| `supervisors` | `evaluations.supervisor_id` | 1 to many | Cascade |
| `evaluations` | `evaluation_scores.evaluation_id` | 1 to many | Cascade |
| `evaluation_criteria` | `evaluation_scores.criteria_id` | 1 to many | Cascade |

## Important implementation notes

1. **No coordinator subtype table:** coordinator identity is represented by
   `users.role = 'coordinator'`.
2. **Status and flag domains:** enum values are enforced by MySQL/MariaDB;
   flags use `TINYINT(1)`. Application validation may impose extra rules.
3. **Application-enforced relationships:** a student may have at most one
   active placement, an attendance student's ID must agree with the session's
   placement, and an evaluation's student/supervisor must agree with its
   placement. These cross-column/business rules are not fully expressed by
   foreign keys in the schema.
4. **Calculated values:** approved hours, hours remaining, progress
   percentage, and weighted evaluation average are calculated by application
   queries/helpers; they are not stored as columns.
5. **Sensitive data:** `password_hash`, `token_hash`, `attendance_token`,
   contact details, private file metadata, IP addresses, and audit details
   must be handled according to the application's authorization rules.
6. **Units:** `required_hours` and `rendered_hours` are hours with two decimal
   places; `max_file_bytes` and `file_size` are bytes; evaluation `rating` is
   a 1–5 integer; `weight` is a non-negative relative weight.
7. **No standalone `coordinators` table, `attendance` table,
   `requirement_submissions` table, or single-rating evaluation field exists
   in this physical schema.** Attendance is `attendance_records`; document
   uploads are `document_submissions`; ratings are `evaluation_scores`.
