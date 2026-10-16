# Internship Tracking System — ERD

Entity-Relationship Diagram (proposed database design), based on the system
features. Uses crow's-foot notation with `1 : many` / `1 : 0..1` cardinality.

- **Rendered image:** [`ERD.png`](ERD.png)
- **Viewable / printable page:** [`erd.html`](erd.html) (open in a browser → Print → Save as PDF)
- **Mermaid source:** [`erd.mmd`](erd.mmd)

> The detailed **physical** schema actually implemented in the code is in
> [`EERD.md`](EERD.md) / [`EERD.png`](EERD.png).

---

## Entities and fields

| Entity | Fields |
|---|---|
| `USERS` | `user_id` PK, `username`, `password_hash`, `role`, `email`, `created_at` |
| `STUDENTS` | `student_id` PK, `user_id` FK, `student_number`, `full_name`, `course_year_section`, `contact_number` |
| `COORDINATORS` | `coordinator_id` PK, `user_id` FK, `name`, `department` |
| `COMPANIES` | `company_id` PK, `company_name`, `address`, `contact_person`, `contact_email` |
| `SUPERVISORS` | `supervisor_id` PK, `company_id` FK, `full_name`, `email`, `contact_number` |
| `INTERNSHIPS` (Placements) | `internship_id` PK, `student_id` FK, `company_id` FK, `supervisor_id` FK, `start_date`, `end_date`, `status` |
| `REQUIREMENTS` | `requirement_id` PK, `requirement_name`, `description`, `deadline` |
| `REQUIREMENT_SUBMISSIONS` | `submission_id` PK, `requirement_id` FK, `student_id` FK, `document_path`, `submitted_at`, `status`, `reviewed_by` FK |
| `ATTENDANCE` | `attendance_id` PK, `internship_id` FK, `attendance_date`, `time_in`, `time_out`, `hours_rendered`, `qr_scan_reference` |
| `EVALUATIONS` | `evaluation_id` PK, `internship_id` FK, `supervisor_id` FK, `rating`, `feedback`, `evaluation_date` |
| `NOTIFICATIONS` | `notification_id` PK, `user_id` FK, `message`, `created_at`, `read_status` |

---

## Relationships

| From | To | Cardinality |
|---|---|---|
| `USERS` | `STUDENTS` | 1 : 0..1 |
| `USERS` | `COORDINATORS` | 1 : 0..1 |
| `USERS` | `SUPERVISORS` | 1 : 0..1 |
| `COMPANIES` | `SUPERVISORS` | 1 : many |
| `COMPANIES` | `INTERNSHIPS` | 1 : many |
| `STUDENTS` | `INTERNSHIPS` | 1 : many |
| `SUPERVISORS` | `INTERNSHIPS` | 1 : many |
| `REQUIREMENTS` | `REQUIREMENT_SUBMISSIONS` | 1 : many |
| `STUDENTS` | `REQUIREMENT_SUBMISSIONS` | 1 : many |
| `INTERNSHIPS` | `ATTENDANCE` | 1 : many |
| `INTERNSHIPS` | `EVALUATIONS` | 1 : many |
| `SUPERVISORS` | `EVALUATIONS` | 1 : many |
| `USERS` | `NOTIFICATIONS` | 1 : many |

---

## Note vs. the implemented database

This diagram is the **proposed** design. The running system differs slightly:

- It has **no `COORDINATORS` table** — a coordinator is a `USERS` row with
  `role = 'coordinator'` (this is the "specialization" in the EERD).
- Some field names differ (e.g. `course_year_section` is stored as
  `course` + `year_level`; `document_path` is split into `stored_name`,
  `original_name`, `mime_type`, `file_size`; `EVALUATIONS` uses multiple
  `evaluation_scores` against `evaluation_criteria` instead of a single
  `rating`).

If you want the **database itself** updated to exactly match this ERD
(adding a `coordinators` table, renaming fields, etc.), that can be done.
